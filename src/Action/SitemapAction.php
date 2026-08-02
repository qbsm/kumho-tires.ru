<?php

declare(strict_types=1);

namespace App\Action;

use App\Support\CitySlugger;
use App\Support\Json;
use App\Support\PlatformSettings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Генерация sitemap.xml с учётом мультиязычности и hreflang.
 * Список страниц берётся из config: settings['sitemap_pages'] (массив page_id).
 */
final class SitemapAction
{
    /** @var array<string, mixed> */
    private array $settings;

    /** @param array<string, mixed> $settings */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $uri = $request->getUri();
        $base = $uri->getScheme() . '://' . $uri->getHost();
        $path = $uri->getPath();
        if ($path !== '' && $path !== '/') {
            $base .= rtrim(dirname($path), '/');
        }
        $base = rtrim($base, '/');

        $langs = PlatformSettings::availableLangs($this->settings);
        $defaultLang = PlatformSettings::defaultLang($this->settings);
        $routeMap = PlatformSettings::routeMap($this->settings);

        $sitemapPages = (array) ($this->settings['sitemap_pages'] ?? []);
        $urls = $this->buildUrls($base, $langs, $defaultLang, $routeMap, $sitemapPages);

        $dynamicPages = (array) ($this->settings['sitemap_dynamic_pages'] ?? []);
        $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');
        if ($dynamicPages !== [] && $jsonBaseDir !== '') {
            $urls = array_merge(
                $urls,
                $this->buildDynamicUrls($base, $langs, $defaultLang, $routeMap, $dynamicPages, $jsonBaseDir)
            );
        }

        $xml = $this->renderSitemap($base, $urls);

        $response->getBody()->write($xml);

        return $response
            ->withHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->withStatus(200);
    }

    /**
     * @param array<int, string> $langs
     * @param array<string, string> $routeMap slug => page_id
     * @param array<int, string> $sitemapPages page_id для включения в sitemap
     * @return array<int, array{loc: string, alternates: array<string, string>, lastmod?: string}>
     */
    private function buildUrls(string $base, array $langs, string $defaultLang, array $routeMap, array $sitemapPages): array
    {
        $reverseMap = array_flip($routeMap);
        $urls = [];

        foreach ($sitemapPages as $pageId) {
            $pathSegment = $this->pageIdToPathSegment($pageId, $reverseMap);

            foreach ($langs as $lang) {
                $loc = $this->buildLangPath($base, $lang, $defaultLang, $pathSegment);
                $alternates = [];
                foreach ($langs as $altLang) {
                    $alternates[$altLang] = $this->buildLangPath($base, $altLang, $defaultLang, $pathSegment);
                }
                $urls[] = ['loc' => $loc, 'alternates' => $alternates];
            }
        }

        return $urls;
    }

    private function pageIdToPathSegment(string $pageId, array $reverseMap): string
    {
        if ($pageId === 'index') {
            return '';
        }
        return (string) ($reverseMap[$pageId] ?? $pageId);
    }

    /**
     * Раскрывает динамические подпути (например, /buy/<city>/) для каждого языка.
     *
     * @param array<int, string> $langs
     * @param array<string, string> $routeMap
     * @param array<string, array<string, mixed>> $dynamicPages
     * @return array<int, array{loc: string, alternates: array<string, string>, lastmod?: string}>
     */
    private function buildDynamicUrls(
        string $base,
        array $langs,
        string $defaultLang,
        array $routeMap,
        array $dynamicPages,
        string $jsonBaseDir
    ): array {
        $reverseMap = array_flip($routeMap);
        $urls = [];

        foreach ($dynamicPages as $pageId => $config) {
            $pathSegment = $this->pageIdToPathSegment((string) $pageId, $reverseMap);
            $dataPage = (string) ($config['data_page'] ?? '');
            $listKey = (string) ($config['list_key'] ?? '');
            $valueKey = (string) ($config['value_key'] ?? '');
            $sluggerKey = (string) ($config['slugger'] ?? 'city');
            $entityDir = (string) ($config['entity_dir'] ?? '');
            if ($pathSegment === '' || $dataPage === '' || $listKey === '') {
                continue;
            }

            // Slug-набор одинаковый для всех языков: данные дилеров — это адреса/названия
            // на родном языке, перевод не предполагается. Берём slug-набор из дефолтного языка.
            $slugs = $this->loadDynamicSlugs($jsonBaseDir, $defaultLang, $dataPage, $listKey, $valueKey, $sluggerKey, $entityDir);
            if ($slugs === []) {
                continue;
            }

            foreach ($slugs as $subSlug) {
                $lastmod = $entityDir !== '' ? $this->entityLastmod($jsonBaseDir, $defaultLang, $entityDir, $subSlug) : null;
                foreach ($langs as $lang) {
                    $loc = $this->buildLangPath($base, $lang, $defaultLang, $pathSegment . '/' . $subSlug);
                    $alternates = [];
                    foreach ($langs as $altLang) {
                        $alternates[$altLang] = $this->buildLangPath($base, $altLang, $defaultLang, $pathSegment . '/' . $subSlug);
                    }
                    $url = ['loc' => $loc, 'alternates' => $alternates];
                    if ($lastmod !== null) {
                        $url['lastmod'] = $lastmod;
                    }
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    private function buildLangPath(string $base, string $lang, string $defaultLang, string $pathSegment): string
    {
        if ($pathSegment === '') {
            return $base . ($lang === $defaultLang ? '/' : '/' . $lang);
        }
        $prefix = $lang === $defaultLang ? '' : '/' . $lang;
        return $base . $prefix . '/' . $pathSegment;
    }

    /**
     * @return array<int, string>
     */
    private function loadDynamicSlugs(
        string $jsonBaseDir,
        string $lang,
        string $dataPage,
        string $listKey,
        string $valueKey,
        string $sluggerKey,
        string $entityDir = ''
    ): array {
        $file = $jsonBaseDir . '/' . $lang . '/pages/' . $dataPage . '.json';
        $items = Json::loadKey($file, $listKey);
        if ($items === null) {
            // Страницы-конструкторы держат список внутри секции (sections[].data[listKey]),
            // а не в корне JSON — например, новости.
            $items = $this->loadListFromSections($file, $listKey);
        }
        if ($items === null) {
            return [];
        }

        $slugs = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $value = $item;
            } elseif (is_array($item) && $valueKey !== '' && isset($item[$valueKey]) && is_string($item[$valueKey])) {
                $value = (string) $item[$valueKey];
            } else {
                continue;
            }
            $slug = $this->slugifyValue($value, $sluggerKey);
            if ($slug === '' || in_array($slug, $slugs, true)) {
                continue;
            }
            if ($entityDir !== '' && !$this->isEntityVisible($jsonBaseDir, $lang, $entityDir, $slug)) {
                continue;
            }
            $slugs[] = $slug;
        }
        sort($slugs);
        return $slugs;
    }

    /**
     * Дата обновления сущности для <lastmod>: поле date_iso в корне или во вложенном
     * item-объекте (news.date_iso). W3C Datetime допускает точность до месяца (YYYY-MM).
     * Не выдумываем дату из mtime файлов: на FTP-проде mtime отражает выкладку, не правку.
     */
    private function entityLastmod(string $jsonBaseDir, string $lang, string $entityDir, string $slug): ?string
    {
        $data = Json::load($jsonBaseDir . '/' . $lang . '/' . $entityDir . '/' . $slug . '.json');
        if ($data === null) {
            return null;
        }
        $candidates = [$data['date_iso'] ?? null];
        foreach ($data as $value) {
            if (is_array($value) && isset($value['date_iso'])) {
                $candidates[] = $value['date_iso'];
            }
        }
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && preg_match('/^\d{4}(-\d{2}){1,2}$/', $candidate) === 1) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Список элементов из секций страницы: sections[].data[$listKey].
     *
     * @return array<int, mixed>|null
     */
    private function loadListFromSections(string $file, string $listKey): ?array
    {
        $sections = Json::loadKey($file, 'sections');
        if ($sections === null) {
            return null;
        }

        $items = [];
        foreach ($sections as $section) {
            if (!is_array($section) || !isset($section['data']) || !is_array($section['data'])) {
                continue;
            }
            $list = $section['data'][$listKey] ?? null;
            if (is_array($list)) {
                foreach ($list as $item) {
                    $items[] = $item;
                }
            }
        }

        return $items === [] ? null : $items;
    }

    /**
     * Сущность попадает в sitemap только если её JSON существует и не скрыт (visible !== false) —
     * иначе страница отдаёт 404 (та же логика, что DataLoaderService::loadEntity).
     */
    private function isEntityVisible(string $jsonBaseDir, string $lang, string $entityDir, string $slug): bool
    {
        $data = Json::load($jsonBaseDir . '/' . $lang . '/' . $entityDir . '/' . $slug . '.json');
        if ($data === null) {
            return false;
        }
        return !(isset($data['visible']) && $data['visible'] === false);
    }

    private function slugifyValue(string $value, string $sluggerKey): string
    {
        return match ($sluggerKey) {
            'identity', 'slug' => trim($value),
            'city' => CitySlugger::slug($value),
            default => CitySlugger::slug($value),
        };
    }

    /**
     * @param array<int, array{loc: string, alternates: array<string, string>, lastmod?: string}> $urls
     */
    private function renderSitemap(string $base, array $urls): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $u) {
            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            if (isset($u['lastmod']) && is_string($u['lastmod']) && $u['lastmod'] !== '') {
                $out .= '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
            }
            foreach ($u['alternates'] as $hreflang => $href) {
                $out .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($hreflang, ENT_XML1, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_XML1, 'UTF-8') . '"/>' . "\n";
            }
            $out .= '  </url>' . "\n";
        }

        $out .= '</urlset>';
        return $out;
    }
}
