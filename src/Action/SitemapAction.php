<?php

declare(strict_types=1);

namespace App\Action;

use App\Support\CitySlugger;
use App\Support\DynamicSlugs;
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

        foreach ((array) ($this->settings['sitemap_extra_paths'] ?? []) as $extraPath) {
            $extraPath = trim((string) $extraPath, '/');
            if ($extraPath === '') {
                continue;
            }
            $alternates = [];
            foreach ($langs as $altLang) {
                $alternates[$altLang] = $this->buildLangPath($base, $altLang, $defaultLang, $extraPath);
            }
            $extraLastmod = $this->sourceLastmod($jsonBaseDir, $defaultLang, $extraPath);
            foreach ($langs as $lang) {
                $url = [
                    'loc' => $this->buildLangPath($base, $lang, $defaultLang, $extraPath),
                    'alternates' => $alternates,
                ];
                if ($extraLastmod !== null) {
                    $url['lastmod'] = $extraLastmod;
                }
                $urls[] = $url;
            }
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

        $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');

        foreach ($sitemapPages as $pageId) {
            $pathSegment = $this->pageIdToPathSegment($pageId, $reverseMap);

            foreach ($langs as $lang) {
                $loc = $this->buildLangPath($base, $lang, $defaultLang, $pathSegment);
                $alternates = [];
                foreach ($langs as $altLang) {
                    $alternates[$altLang] = $this->buildLangPath($base, $altLang, $defaultLang, $pathSegment);
                }
                $url = ['loc' => $loc, 'alternates' => $alternates];
                // Дата правки контента страницы — подсказка роботу, что переобходить
                $pageFile = $jsonBaseDir . '/' . $lang . '/pages/' . $pageId . '.json';
                if ($jsonBaseDir !== '' && is_file($pageFile)) {
                    $mtime = filemtime($pageFile);
                    if ($mtime !== false) {
                        $url['lastmod'] = date('Y-m-d', $mtime);
                    }
                }
                $urls[] = $url;
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
            $slugs = DynamicSlugs::list($jsonBaseDir, $defaultLang, [
                'data_page' => $dataPage,
                'list_key' => $listKey,
                'value_key' => $valueKey,
                'slugger' => $sluggerKey,
                'entity_dir' => $entityDir,
            ]);
            if ($slugs === []) {
                continue;
            }

            foreach ($slugs as $subSlug) {
                $lastmod = $entityDir !== ''
                    ? $this->entityLastmod($jsonBaseDir, $defaultLang, $entityDir, $subSlug)
                    : $this->fileLastmod($jsonBaseDir . '/' . $defaultLang . '/pages/' . $dataPage . '.json');
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
     * Дата обновления сущности для <lastmod>: поле date_iso в корне или во вложенном
     * item-объекте (news.date_iso). W3C Datetime допускает точность до месяца (YYYY-MM).
     * Не выдумываем дату из mtime файлов: на FTP-проде mtime отражает выкладку, не правку.
     */
    /**
     * Дата правки файла-источника. Для страниц, собираемых из данных (города, разделы фильтра),
     * своей даты в контенте нет: честный признак изменения — когда поменялись сами данные.
     */
    private function fileLastmod(string $file): ?string
    {
        if (!is_file($file)) {
            return null;
        }
        $ts = @filemtime($file);

        return $ts === false ? null : date('Y-m-d', $ts);
    }

    /**
     * Дата для человечного адреса фильтра: сначала файл текста раздела (filters/<коллекция>-<slug>.json),
     * иначе список сущностей коллекции — состав раздела меняется вместе с ним.
     */
    private function sourceLastmod(string $jsonBaseDir, string $lang, string $extraPath): ?string
    {
        $parts = explode('/', trim($extraPath, '/'));
        if (count($parts) < 2) {
            return null;
        }
        $collection = $parts[0];
        $slug = $parts[count($parts) - 1];

        return $this->fileLastmod($jsonBaseDir . '/' . $lang . '/filters/' . $collection . '-' . $slug . '.json')
            ?? $this->fileLastmod($jsonBaseDir . '/' . $lang . '/pages/' . $collection . '.json');
    }

    private function entityLastmod(string $jsonBaseDir, string $lang, string $entityDir, string $slug): ?string
    {
        $file = $jsonBaseDir . '/' . $lang . '/' . $entityDir . '/' . $slug . '.json';
        $data = Json::load($file);
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
            if (!is_string($candidate)) {
                continue;
            }
            // Sitemap требует дату в формате W3C Datetime; «2026-03» без дня Яндекс отбраковывает
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate) === 1) {
                return $candidate;
            }
            if (preg_match('/^\d{4}-\d{2}$/', $candidate) === 1) {
                return $candidate . '-01';
            }
        }

        // У сущностей без даты в данных (модели шин) ориентир — время правки их файла
        $mtime = is_file($file) ? filemtime($file) : false;
        return $mtime === false ? null : date('Y-m-d', $mtime);
    }




    /**
     * @param array<int, array{loc: string, alternates: array<string, string>, lastmod?: string}> $urls
     */
    private function renderSitemap(string $base, array $urls): string
    {
        $hasAlternates = false;
        foreach ($urls as $u) {
            if (count($u['alternates']) > 1) {
                $hasAlternates = true;
                break;
            }
        }

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ($hasAlternates ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '') . '>' . "\n";

        foreach ($urls as $u) {
            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            if (isset($u['lastmod']) && $u['lastmod'] !== '') {
                $out .= '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
            }
            // Одноязычный сайт: alternate сам на себя — лишний узел, который валидаторы считают ошибкой
            foreach (count($u['alternates']) > 1 ? $u['alternates'] : [] as $hreflang => $href) {
                $out .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($hreflang, ENT_XML1, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_XML1, 'UTF-8') . '"/>' . "\n";
            }
            $out .= '  </url>' . "\n";
        }

        $out .= '</urlset>';
        return $out;
    }
}
