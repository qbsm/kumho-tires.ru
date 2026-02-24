<?php

declare(strict_types=1);

namespace App\Action;

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

        $langs = (array) ($this->settings['available_langs'] ?? ['ru', 'en']);
        $defaultLang = (string) ($this->settings['default_lang'] ?? 'ru');
        $routeMap = (array) ($this->settings['route_map'] ?? []);

        $sitemapPages = (array) ($this->settings['sitemap_pages'] ?? []);
        $urls = $this->buildUrls($base, $langs, $defaultLang, $routeMap, $sitemapPages);

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
     * @return array<int, array{loc: string, alternates: array<string, string>}>
     */
    private function buildUrls(string $base, array $langs, string $defaultLang, array $routeMap, array $sitemapPages): array
    {
        $reverseMap = array_flip($routeMap);
        $urls = [];

        foreach ($sitemapPages as $pageId) {
            $pathSegment = $this->pageIdToPathSegment($pageId, $reverseMap);

            foreach ($langs as $lang) {
                if ($lang === $defaultLang) {
                    $path = $pathSegment === '' ? '/' : '/' . $pathSegment . '/';
                } else {
                    $path = $pathSegment === '' ? '/' . $lang . '/' : '/' . $lang . '/' . $pathSegment . '/';
                }
                $loc = $base . $path;

                $alternates = [];
                foreach ($langs as $altLang) {
                    if ($altLang === $defaultLang) {
                        $altPath = $pathSegment === '' ? '/' : '/' . $pathSegment . '/';
                    } else {
                        $altPath = $pathSegment === '' ? '/' . $altLang . '/' : '/' . $altLang . '/' . $pathSegment . '/';
                    }
                    $alternates[$altLang] = $base . $altPath;
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
     * @param array<int, array{loc: string, alternates: array<string, string>}> $urls
     */
    private function renderSitemap(string $base, array $urls): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $u) {
            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            foreach ($u['alternates'] as $hreflang => $href) {
                $out .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($hreflang, ENT_XML1, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_XML1, 'UTF-8') . '"/>' . "\n";
            }
            $out .= '  </url>' . "\n";
        }

        $out .= '</urlset>';
        return $out;
    }
}
