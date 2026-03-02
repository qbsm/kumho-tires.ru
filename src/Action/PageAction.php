<?php

namespace App\Action;

use App\Service\DataLoaderService;
use App\Service\SeoService;
use App\Service\TemplateDataBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;
use Twig\Environment;

final class PageAction
{
    /** @var array<string,mixed> */
    private array $settings;

    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(
        Twig $twig,
        DataLoaderService $dataLoader,
        SeoService $seoService,
        TemplateDataBuilder $templateDataBuilder,
        array $settings
    ) {
        $this->twig = $twig;
        $this->dataLoader = $dataLoader;
        $this->seoService = $seoService;
        $this->templateDataBuilder = $templateDataBuilder;
        $this->settings = $settings;
    }

    private Twig $twig;
    private DataLoaderService $dataLoader;
    private SeoService $seoService;
    private TemplateDataBuilder $templateDataBuilder;

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $csrfToken = $this->ensureCsrfToken();
        $segments = $request->getAttribute('segments', []);
        $baseUrl = (string) $request->getAttribute('base_url', '/');
        $global = $request->getAttribute('global', []);
        $langCode = (string) $request->getAttribute('lang_code', $this->settings['default_lang'] ?? 'ru');
        $currentLang = $request->getAttribute('current_lang', ['code' => $langCode]);
        $isLangInUrl = (bool) $request->getAttribute('is_lang_in_url', false);

        $pageId = 'index';
        $routeParams = [];
        if (!empty($segments)) {
            $slug = (string) $segments[0];
            $routeMap = (array) ($this->settings['route_map'] ?? []);
            $pageId = (string) ($routeMap[$slug] ?? $slug);
            $routeParams = array_slice($segments, 1);
        }

        $pageDirTemplate = (string) ($this->settings['paths']['json_pages_dir'] ?? '');
        $pageJsonDir = str_replace('{lang}', $langCode, $pageDirTemplate);
        $pageData = $this->dataLoader->loadPage($pageJsonDir, $pageId, $baseUrl);

        $status = 200;
        $tire = null;
        $tireBreadcrumb = null;
        $news = null;
        $newsBreadcrumb = null;

        if ($pageData === null) {
            $slug = (string) ($segments[0] ?? '');
            $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');
            $tireSlugs = $this->dataLoader->loadTireSlugs($jsonBaseDir, $langCode);
            if ($slug !== '' && $tireSlugs !== null && in_array($slug, $tireSlugs, true)) {
                $tire = $this->dataLoader->loadTire($jsonBaseDir, $langCode, $slug, $baseUrl);
            }
            if ($tire !== null) {
                $pageId = $slug;
                $routeParams = [];
                $pageData = ['name' => $slug, 'sections' => []];
            } else {
                $status = 404;
                $pageId = '404';
                $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
            }
        }

        $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');
        if ($tire === null && $pageId === 'news' && count($routeParams) === 0) {
            $this->injectNewsListItems($pageData, $jsonBaseDir, $langCode, $baseUrl);
        }
        if ($tire === null && $pageId === 'news' && count($routeParams) === 1) {
            $newsSlug = (string) $routeParams[0];
            $newsSlugs = $this->dataLoader->loadNewsSlugs($jsonBaseDir, $langCode);
            if ($newsSlugs !== null && in_array($newsSlug, $newsSlugs, true)) {
                $news = $this->dataLoader->loadNews($jsonBaseDir, $langCode, $newsSlug, $baseUrl);
            }
            if ($news !== null) {
                $pageData = ['name' => $newsSlug, 'sections' => []];
                $newsBreadcrumb = $this->buildNewsBreadcrumb($global, $langCode, $news);
            } else {
                $status = 404;
                $pageId = '404';
                $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
            }
        }

        $seoData = $this->dataLoader->loadSeo($jsonBaseDir, $langCode, $pageId, $baseUrl);

        if ($tire !== null) {
            $seoData = $this->buildSeoForTire($tire, $baseUrl);
            $tireBreadcrumb = $this->buildTireBreadcrumb($global, $langCode, $tire);
        } elseif ($news !== null) {
            $seoData = $this->buildSeoForNews($news, $baseUrl);
        }

        if ($seoData !== null) {
            $twigEnv = $this->twig->getEnvironment();
            $seoData = $this->seoService->processTemplates($seoData, [
                'pageData' => $pageData,
                'global' => $global,
                'settings' => $this->settings,
                'currentLang' => $currentLang,
                'lang_code' => $langCode,
                'route_params' => $routeParams,
                'base_url' => $baseUrl,
                'is_lang_in_url' => $isLangInUrl,
            ], $twigEnv);
        } else {
            $seoData = ['title' => '', 'meta' => [], 'json_ld' => null];
        }

        $template = $tire !== null ? 'pages/tire.twig' : ($news !== null ? 'pages/news.twig' : 'pages/page.twig');

        $extras = [];
        if ($tire !== null) {
            $extras['tire'] = $tire;
            $extras['breadcrumb'] = $tireBreadcrumb;
        } elseif ($news !== null) {
            $extras['news'] = $news;
            $extras['breadcrumb'] = $newsBreadcrumb;
        }

        $data = $this->templateDataBuilder->build(
            $this->settings,
            is_array($global) ? $global : [],
            $pageData,
            $seoData,
            [
                'current_lang' => is_array($currentLang) ? $currentLang : ['code' => $langCode],
                'lang_code' => $langCode,
                'page_id' => $pageId,
                'route_params' => $routeParams,
                'base_url' => $baseUrl,
                'is_lang_in_url' => $isLangInUrl,
                'csrf_token' => $csrfToken,
            ],
            $extras
        );

        return $this->twig->render($response->withStatus($status), $template, $data);
    }

    /**
     * @param array<string,mixed> $tire
     * @return array<string,mixed>
     */
    private function buildSeoForTire(array $tire, string $baseUrl): array
    {
        $t = $tire['item'] ?? [];
        $name = (string) ($t['name'] ?? $tire['slug'] ?? '');
        $desc = (string) ($tire['desc']['short'] ?? $tire['desc']['full'] ?? '');

        return [
            'title' => $name,
            'meta' => [
                ['name' => 'description', 'content' => $desc],
                ['property' => 'og:type', 'content' => 'website'],
                ['property' => 'og:title', 'content' => $name],
                ['property' => 'og:description', 'content' => $desc],
            ],
            'json_ld' => null,
            'json_ld_faq' => null,
        ];
    }

    /**
     * @param array<string,mixed> $tire
     * @return array<int, array{name: string, url: string}>
     */
    private function buildTireBreadcrumb(array $global, string $langCode, array $tire): array
    {
        $t = $tire['item'] ?? [];
        $name = (string) ($t['name'] ?? $tire['slug'] ?? '');
        $nav = $global['nav'][$langCode]['items'] ?? [];
        $homeTitle = 'Главная';
        $listTitle = 'Шины';
        $listHref = '/tires/';
        foreach ($nav as $item) {
            if (!is_array($item)) {
                continue;
            }
            $href = trim((string) ($item['href'] ?? ''), '/');
            if ($href === '' || $href === '/') {
                $homeTitle = (string) ($item['title'] ?? $homeTitle);
            }
            if ($href === 'tires') {
                $listTitle = (string) ($item['title'] ?? $listTitle);
                $listHref = '/' . $href . '/';
            }
        }
        return [
            ['name' => $homeTitle, 'url' => '/'],
            ['name' => $listTitle, 'url' => $listHref],
            ['name' => $name, 'url' => '/' . $tire['slug'] . '/'],
        ];
    }

    /**
     * Заполняет секцию "news" на странице /news/ данными карточек из загруженных новостей.
     *
     * @param array<string,mixed> $pageData
     */
    private function injectNewsListItems(array &$pageData, string $jsonBaseDir, string $langCode, string $baseUrl): void
    {
        $slugs = [];

        $topLevelItems = $pageData['items'] ?? [];
        if (is_array($topLevelItems) && $topLevelItems !== []) {
            foreach ($topLevelItems as $item) {
                if (is_string($item) && $item !== '') {
                    $slugs[] = $item;
                    continue;
                }
                if (is_array($item) && isset($item['slug']) && is_string($item['slug']) && $item['slug'] !== '') {
                    $slugs[] = $item['slug'];
                }
            }
        }

        $sections = &$pageData['sections'];
        if (!is_array($sections)) {
            return;
        }

        if ($slugs === []) {
            foreach ($sections as $section) {
                if (
                    !is_array($section)
                    || ($section['name'] ?? '') !== 'news'
                    || !isset($section['data'])
                    || !is_array($section['data'])
                    || !isset($section['data']['items'])
                    || !is_array($section['data']['items'])
                ) {
                    continue;
                }

                foreach ($section['data']['items'] as $item) {
                    if (is_string($item) && $item !== '') {
                        $slugs[] = $item;
                        continue;
                    }
                    if (is_array($item) && isset($item['slug']) && is_string($item['slug']) && $item['slug'] !== '') {
                        $slugs[] = $item['slug'];
                    }
                }
                break;
            }
        }

        $slugs = array_values(array_unique($slugs));
        if ($slugs === []) {
            return;
        }

        $items = [];
        foreach ($slugs as $newsSlug) {
            $news = $this->dataLoader->loadNews($jsonBaseDir, $langCode, (string) $newsSlug, $baseUrl);
            if ($news === null) {
                continue;
            }
            $n = $news['news'] ?? [];
            $items[] = [
                'slug' => $news['slug'] ?? $newsSlug,
                'cover' => $n['cover'] ?? ['src' => ''],
                'date' => $n['date'] ?? '',
                'title' => $n['title'] ?? '',
                'desc' => $n['desc'] ?? $n['lead'] ?? '',
            ];
        }
        foreach ($sections as $idx => $section) {
            if (isset($section['name']) && $section['name'] === 'news' && isset($section['data'])) {
                $sections[$idx]['data']['items'] = $items;
                return;
            }
        }
    }

    /**
     * @param array<string,mixed> $news
     * @return array<string,mixed>
     */
    private function buildSeoForNews(array $news, string $baseUrl): array
    {
        $n = $news['news'] ?? [];
        $title = (string) ($n['title'] ?? $news['slug'] ?? '');
        $desc = (string) ($n['desc'] ?? $n['lead'] ?? '');

        return [
            'title' => $title,
            'meta' => [
                ['name' => 'description', 'content' => $desc],
                ['property' => 'og:type', 'content' => 'article'],
                ['property' => 'og:title', 'content' => $title],
                ['property' => 'og:description', 'content' => $desc],
            ],
            'json_ld' => null,
            'json_ld_faq' => null,
        ];
    }

    /**
     * @param array<string,mixed> $news
     * @return array<int, array{name: string, url: string}>
     */
    private function buildNewsBreadcrumb(array $global, string $langCode, array $news): array
    {
        $n = $news['news'] ?? [];
        $name = (string) ($n['title'] ?? $news['slug'] ?? '');
        $nav = $global['nav'][$langCode]['items'] ?? [];
        $homeTitle = 'Главная';
        $listTitle = 'Новости';
        $listHref = '/news/';
        foreach ($nav as $item) {
            if (!is_array($item)) {
                continue;
            }
            $href = trim((string) ($item['href'] ?? ''), '/');
            if ($href === '' || $href === '/') {
                $homeTitle = (string) ($item['title'] ?? $homeTitle);
            }
            if ($href === 'news') {
                $listTitle = (string) ($item['title'] ?? $listTitle);
                $listHref = '/' . $href . '/';
            }
        }
        return [
            ['name' => $homeTitle, 'url' => '/'],
            ['name' => $listTitle, 'url' => $listHref],
            ['name' => $name, 'url' => '/news/' . $news['slug'] . '/'],
        ];
    }

    private function ensureCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}
