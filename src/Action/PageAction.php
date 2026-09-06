<?php

namespace App\Action;

use App\Support\DynamicSlugs;
use App\Event\EntityResolved;
use App\Event\PageLoaded;
use App\Event\SeoBuilt;
use App\Service\DataLoaderService;
use App\Service\SeoBuilderRegistry;
use App\Service\SeoService;
use App\Service\TemplateDataBuilder;
use Psr\EventDispatcher\EventDispatcherInterface;
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
        array $settings,
        ?EventDispatcherInterface $dispatcher = null,
        ?SeoBuilderRegistry $seoBuilderRegistry = null,
    ) {
        $this->twig = $twig;
        $this->dataLoader = $dataLoader;
        $this->seoService = $seoService;
        $this->templateDataBuilder = $templateDataBuilder;
        $this->settings = $settings;
        $this->dispatcher = $dispatcher;
        $this->seoBuilderRegistry = $seoBuilderRegistry;
    }

    private Twig $twig;
    private DataLoaderService $dataLoader;
    private SeoService $seoService;
    private TemplateDataBuilder $templateDataBuilder;
    private ?EventDispatcherInterface $dispatcher;
    private ?SeoBuilderRegistry $seoBuilderRegistry;

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
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

        // Наследие query-фильтра (?season=summer&diameter=17): такие адреса остались в индексе
        // с прошлой схемы каталога и вели на общий /tires, забирая сигналы у сезонных посадочных.
        $legacy = $this->legacyFilterRedirect($pageId, $routeParams, $request->getQueryParams());
        if ($legacy !== null) {
            return $response->withStatus(301)->withHeader('Location', rtrim($baseUrl, '/') . $legacy);
        }

        $pageDirTemplate = (string) ($this->settings['paths']['json_pages_dir'] ?? '');
        $pageJsonDir = str_replace('{lang}', $langCode, $pageDirTemplate);
        $pageData = $this->dataLoader->loadPage($pageJsonDir, $pageId, $baseUrl);

        $collections = (array) ($this->settings['collections'] ?? []);
        $jsonBaseDir = (string) ($this->settings['paths']['json_base'] ?? '');

        if ($pageData !== null) {
            $this->dataLoader->injectItemsFrom($pageData, $collections, $jsonBaseDir, $langCode, $baseUrl);
        }

        $status = 200;
        $entity = null;
        $entityType = '';
        $entityConfig = [];

        if ($pageData === null) {
            $slug = (string) ($segments[0] ?? '');
            if ($slug !== '') {
                foreach ($collections as $collKey => $collConfig) {
                    $collConfig = (array) $collConfig;
                    $loaded = $this->dataLoader->loadEntity($jsonBaseDir, $langCode, $slug, $baseUrl, $collConfig, true);
                    if ($loaded !== null) {
                        $entity = $loaded;
                        $entityType = (string) $collKey;
                        $entityConfig = $collConfig;
                        break;
                    }
                }
            }

            if ($entity !== null) {
                $pageId = $slug;
                $routeParams = [];
                $pageData = ['name' => $slug, 'sections' => []];
                $this->dispatch(new EntityResolved($entityType, $slug, $entity, $entityConfig));
            } else {
                $status = 404;
                $pageId = '404';
                $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
            }
        }

        // Подпуть страницы-конструктора (/buy/<город>) обязан существовать в данных: без проверки
        // любой слаг отдавал 200 и плодил дубли, которых нет ни в карте сайта, ни в навигации.
        if ($status === 200 && $routeParams !== [] && !$this->isCollectionListPage($pageId, $collections)) {
            $subpathConfig = (array) ($this->settings['sitemap_dynamic_pages'][$pageId] ?? []);
            if ($subpathConfig !== []) {
                $allowed = DynamicSlugs::list($jsonBaseDir, $langCode, $subpathConfig);
                if (count($routeParams) > 1 || !in_array((string) $routeParams[0], $allowed, true)) {
                    $status = 404;
                    $pageId = '404';
                    $routeParams = [];
                    $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl)
                        ?? ['name' => '404', 'sections' => []];
                }
            }
        }

        $extrasFilter = null;
        if ($entity === null) {
            foreach ($collections as $collKey => $collConfig) {
                $collConfig = (array) $collConfig;
                $listPageId = (string) ($collConfig['list_page_id'] ?? '');
                if ($pageId !== $listPageId) {
                    continue;
                }

                // Человечные адреса фильтра: /tires/summer, /tires/205-55-r16, /tires/summer/205-55-r16.
                // Проверяем до загрузки сущности — сегменты фильтра не пересекаются со слагами моделей.
                $filterPreset = $this->parseFilterParams($routeParams, $collConfig);
                if ($filterPreset !== null) {
                    $this->injectListItems($pageData, $jsonBaseDir, $langCode, $baseUrl, $collConfig);
                    $extrasFilter = $filterPreset;
                    break;
                }

                if (count($routeParams) === 0) {
                    $this->injectListItems($pageData, $jsonBaseDir, $langCode, $baseUrl, $collConfig);
                } elseif (count($routeParams) === 1) {
                    $subSlug = (string) $routeParams[0];
                    // Доступность страницы = наличие файла сущности, а не членство в items:
                    // скрытые (visible:false) модели и новости остаются доступны по прямой ссылке
                    // (200 + noindex), но отсутствуют в списках и sitemap. Слаг валидируется
                    // внутри loadEntity — защита от path traversal.
                    {
                        $loaded = $this->dataLoader->loadEntity($jsonBaseDir, $langCode, $subSlug, $baseUrl, $collConfig, true);
                        if ($loaded !== null) {
                            $entity = $loaded;
                            $entityType = (string) $collKey;
                            $entityConfig = $collConfig;
                            // Сохраняем sections из list-page (header/hero/list/footer) — entity-страница
                            // переиспользует layout. Hero и list-секции получают entity через extras.
                            $listSections = (isset($pageData['sections']) && is_array($pageData['sections'])) ? $pageData['sections'] : [];
                            $pageData = ['name' => $subSlug, 'sections' => $listSections];
                            $this->dispatch(new EntityResolved($entityType, $subSlug, $entity, $entityConfig));
                        }
                    }
                    if ($entity === null) {
                        $status = 404;
                        $pageId = '404';
                        $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
                    }
                } else {
                    $status = 404;
                    $pageId = '404';
                    $pageData = $this->dataLoader->loadPage($pageJsonDir, '404', $baseUrl) ?? ['name' => '404', 'sections' => []];
                }
                break;
            }
        }

        $this->dispatch(new PageLoaded($pageId, $langCode, $pageData, $status));

        $seoData = $this->dataLoader->loadSeo($jsonBaseDir, $langCode, $pageId, $baseUrl);

        if ($entity !== null) {
            $seoData = $this->buildSeoForEntity($entity, $baseUrl, $langCode, $entityConfig, is_array($global) ? $global : [], $entityType);
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

        // Страница фильтра — сезонная, по линейке моделей или по типоразмеру — самостоятельная
        // посадочная: свой title, description и h1, иначе адреса делят заголовок с общим каталогом
        $filterVariants = (array) ($this->settings['collections']['tires']['filters'] ?? []);
        $filterGroup = '';
        if ($extrasFilter !== null && !isset($extrasFilter['width'])) {
            $filterGroup = isset($extrasFilter['season']) ? 'season' : (isset($extrasFilter['family']) ? 'family' : '');
        }

        $title = '';
        $description = '';
        $heading = '';
        $contentKey = '';

        if ($filterGroup !== '') {
            $filterKey = (string) $extrasFilter[$filterGroup];
            $variants = (array) ($filterVariants[$filterGroup] ?? []);
            $variant = $variants[$filterKey] ?? null;
            $variant = is_array($variant) ? $variant : ['label' => (string) $variant];
            $label = (string) ($variant['label'] ?? '');
            if ($label !== '') {
                $genitive = (string) ($variant['genitive'] ?? mb_strtolower($label));
                if ($filterGroup === 'family') {
                    $title = 'Шины Kumho ' . $label . ' — все модели линейки и типоразмеры';
                    $description = 'Линейка Kumho ' . $label . ' в каталоге официального дистрибьютора: модели, '
                        . 'типоразмеры и характеристики. Фильтр по диаметру, ширине и профилю.';
                } else {
                    $title = $label . ' шины Kumho (Кумхо) — каталог, купить в России';
                    $description = 'Каталог ' . $genitive . ' шин Kumho: модели и типоразмеры для легковых автомобилей, '
                        . 'кроссоверов, внедорожников и коммерческого транспорта. Фильтр по диаметру, ширине и профилю.';
                }
                $heading = (string) ($variant['h1'] ?? ($label . ' шины Kumho'));
                $contentKey = $filterKey;
            }
        } elseif ($extrasFilter !== null && isset($extrasFilter['width'])) {
            // Типоразмер в адресе: посадочная под запросы вида «шины кумхо 205 55 r16»
            $sizeLabel = $extrasFilter['width'] . '/' . $extrasFilter['profile'] . ' R' . $extrasFilter['diameter'];
            $seasonKey = (string) ($extrasFilter['season'] ?? '');
            $seasons = (array) ($filterVariants['season'] ?? []);
            $seasonVariant = $seasons[$seasonKey] ?? null;
            $seasonLabel = '';
            $seasonGenitive = '';
            if (is_array($seasonVariant)) {
                $seasonLabel = (string) ($seasonVariant['label'] ?? '');
                $seasonGenitive = (string) ($seasonVariant['genitive'] ?? mb_strtolower($seasonLabel));
            }

            // Линейка в адресе вместе с размером (/tires/wintercraft/205-55-r16): каталог
            // фильтруется по обоим признакам, поэтому и заголовок обязан называть оба.
            $familyKey = (string) ($extrasFilter['family'] ?? '');
            $familyVariant = ((array) ($filterVariants['family'] ?? []))[$familyKey] ?? null;
            $familyLabel = is_array($familyVariant) ? (string) ($familyVariant['label'] ?? '') : '';
            $familySuffix = $familyLabel !== '' ? ' ' . $familyLabel : '';

            $heading = ($seasonLabel !== '' ? $seasonLabel . ' шины' : 'Шины') . ' Kumho'
                . $familySuffix . ' ' . $sizeLabel;
            $title = $heading . ' — все модели в этом размере';
            $description = ($seasonGenitive !== '' ? 'Каталог ' . $seasonGenitive . ' шин' : 'Каталог шин')
                . ' Kumho' . $familySuffix . ' в размере ' . $sizeLabel . ': модели официального дистрибьютора, '
                . 'характеристики, индексы нагрузки и скорости. Где купить в России.';
        }

        if ($title !== '') {
            $seoData['title'] = $title;
            $meta = isset($seoData['meta']) && is_array($seoData['meta']) ? $seoData['meta'] : [];
            foreach ($meta as $index => $tag) {
                if (!is_array($tag)) {
                    continue;
                }
                if (($tag['name'] ?? '') === 'description' || ($tag['property'] ?? '') === 'og:description') {
                    $meta[$index]['content'] = $description;
                }
                if (($tag['property'] ?? '') === 'og:title') {
                    $meta[$index]['content'] = $seoData['title'];
                }
            }
            $seoData['meta'] = $meta;

            foreach (($pageData['sections'] ?? []) as $idx => $section) {
                if (($section['name'] ?? '') === 'tires' && isset($section['data']['heading']['title'])) {
                    $pageData['sections'][$idx]['data']['heading']['title'] = $heading;
                }
            }
        }

        // Свой текст страницы фильтра вместо общего текста каталога: иначе посадочные
        // отличались бы только заголовком. У типоразмеров своего файла нет — там текст
        // собирается из отфильтрованного списка моделей в шаблоне.
        if ($contentKey !== '') {
            $contentFile = $jsonBaseDir . '/' . $langCode . '/filters/tires-' . $contentKey . '.json';
            $seasonContent = $this->dataLoader->loadJson($contentFile, $baseUrl);
            if (is_array($seasonContent) && isset($seasonContent['content']) && is_array($seasonContent['content'])) {
                foreach (($pageData['sections'] ?? []) as $idx => $section) {
                    if (($section['name'] ?? '') === 'content-container') {
                        $pageData['sections'][$idx]['data']['content'] = $seasonContent['content'];
                    }
                }
            }
        }

        $this->dispatch(new SeoBuilt($pageId, $seoData, $entity !== null));

        $template = 'pages/page.twig';
        $extras = [];
        if ($entity !== null) {
            $template = (string) ($entityConfig['template'] ?? 'pages/page.twig');
            $extrasKey = (string) ($entityConfig['extras_key'] ?? $entityType);
            $extras[$extrasKey] = $entity;
            $extras['entity'] = $entity;
            $extras['breadcrumb'] = $this->buildEntityBreadcrumb($global, $langCode, $entity, $entityConfig, $pageJsonDir, $baseUrl);
            $extras['frame_data'] = $this->extractFrameFromListPage($pageJsonDir, $entityConfig, $baseUrl);
            $extras['seo_url_path'] = trim((string) ($entityConfig['nav_slug'] ?? ''), '/') . '/' . trim((string) ($entity['slug'] ?? ''), '/');
        }

        if ($extrasFilter !== null) {
            $extras['filter_preset'] = $extrasFilter;
            $extras['breadcrumb'] = $this->buildFilterBreadcrumb($global, $langCode, $extrasFilter);
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
            ],
            $extras
        );

        $response = $response->withStatus($status);
        // Скрытая сущность (visible:false) доступна по прямой ссылке, но убирается из индекса:
        // noindex вместо 404 — поисковик деиндексирует URL без ошибок обхода; follow — чтобы вес
        // ссылок со страницы не терялся.
        if ($entity !== null && !empty($entity['_hidden'])) {
            $response = $response->withHeader('X-Robots-Tag', 'noindex, follow');
        }

        // Сезонные страницы фильтра и страницы линеек индексируются: это осмысленные посадочные.
        // Из типоразмеров открыт только белый список ходовых — остальные сотни комбинаций
        // дублируют друг друга и уходят в noindex, follow: вес по ссылкам на модели передаётся.
        // Связка «сезон + размер» закрыта всегда: она повторяет страницу размера.
        if ($extrasFilter !== null && isset($extrasFilter['width'])) {
            $sizeSlug = $extrasFilter['width'] . '-' . $extrasFilter['profile'] . '-r' . $extrasFilter['diameter'];
            $indexableSizes = (array) ($this->settings['collections']['tires']['filters']['indexable_sizes'] ?? []);
            $sizeIsOpen = count($extrasFilter) === 3 && in_array($sizeSlug, $indexableSizes, true);
            if (!$sizeIsOpen) {
                $response = $response->withHeader('X-Robots-Tag', 'noindex, follow');
            }
        }

        return $this->twig->render($response, $template, $data);
    }

    /**
     * @param array<string,mixed> $entity
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    /**
     * Строит SEO для entity-страницы коллекции.
     *
     * Если SeoBuilderRegistry зарегистрирован и содержит builder для коллекции (или default) — делегирует ему.
     * Иначе — inline generic-логика (для обратной совместимости deployments без Registry).
     *
     * @param array<string,mixed> $entity
     * @param array<string,mixed> $config
     * @param array<string,mixed> $global
     * @return array<string,mixed>
     */
    private function buildSeoForEntity(array $entity, string $baseUrl, string $langCode, array $config, array $global, string $entityType): array
    {
        if ($this->seoBuilderRegistry !== null) {
            $builder = $this->seoBuilderRegistry->get($entityType);
            if ($builder !== null) {
                return $builder->build($entity, $baseUrl, $langCode, $config, $global);
            }
        }

        // Inline fallback (legacy-совместимость): generic SEO без специфического Schema.org.
        $itemKey = (string) ($config['item_key'] ?? '');
        $ogType = (string) ($config['og_type'] ?? 'website');
        $siteName = (string) ($global['name'] ?? $global['site_name'] ?? '');

        $inner = $itemKey !== '' ? ($entity[$itemKey] ?? []) : $entity;
        $name = (string) ($inner['name'] ?? $inner['title'] ?? $entity['slug'] ?? '');
        $desc = (string) ($entity['desc']['short'] ?? $entity['desc']['full'] ?? $inner['desc'] ?? $inner['lead'] ?? '');
        $ogImage = rtrim($baseUrl, '/') . '/data/img/seo/og.jpg?v=3';

        $meta = [
            ['name' => 'description', 'content' => $desc],
            ['property' => 'og:type', 'content' => $ogType],
            ['property' => 'og:title', 'content' => $name],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:image', 'content' => $ogImage],
        ];
        if ($siteName !== '') {
            $meta[] = ['property' => 'og:site_name', 'content' => $siteName];
        }

        return [
            'title' => $name,
            'meta' => $meta,
            'json_ld' => null,
            'json_ld_faq' => null,
        ];
    }

    /**
     * @param array<string,mixed> $global
     * @param array<string,mixed> $entity
     * @param array<string,mixed> $config
     * @return array<int, array{name: string, url: string}>
     */
    /**
     * @param array<string,mixed>|null $global
     * @param array<string,string> $filter
     * @return array<int, array{name: string, url: string}>
     */
    private function buildFilterBreadcrumb(?array $global, string $langCode, array $filter): array
    {
        $collConfig = (array) ($this->settings['collections']['tires'] ?? []);
        $navSlug = trim((string) ($collConfig['nav_slug'] ?? 'tires'), '/');
        $nav = is_array($global) ? ($global['nav'][$langCode]['items'] ?? []) : [];

        $homeTitle = 'Главная';
        $listTitle = ucfirst($navSlug);
        foreach ((array) $nav as $navItem) {
            if (!is_array($navItem)) {
                continue;
            }
            $href = trim((string) ($navItem['href'] ?? ''), '/');
            if ($href === '') {
                $homeTitle = (string) ($navItem['title'] ?? $homeTitle);
            }
            if ($href === $navSlug && isset($navItem['title'])) {
                $listTitle = (string) $navItem['title'];
            }
        }

        $items = [
            ['name' => $homeTitle, 'url' => '/'],
            ['name' => $listTitle, 'url' => '/' . $navSlug],
        ];

        $path = '/' . $navSlug;
        if (isset($filter['season'])) {
            $seasons = (array) ($collConfig['filters']['season'] ?? []);
            $season = $seasons[$filter['season']] ?? null;
            $season = is_array($season) ? $season : ['label' => (string) $season];
            $path .= '/' . $filter['season'];
            $items[] = ['name' => (string) ($season['label'] ?? $filter['season']), 'url' => $path];
        }
        if (isset($filter['family'])) {
            $families = (array) ($collConfig['filters']['family'] ?? []);
            $family = $families[$filter['family']] ?? null;
            $family = is_array($family) ? $family : ['label' => (string) $family];
            $path .= '/' . $filter['family'];
            $items[] = ['name' => (string) ($family['label'] ?? $filter['family']), 'url' => $path];
        }
        if (isset($filter['width'], $filter['profile'], $filter['diameter'])) {
            $path .= '/' . $filter['width'] . '-' . $filter['profile'] . '-r' . $filter['diameter'];
            $items[] = [
                'name' => $filter['width'] . '/' . $filter['profile'] . ' R' . $filter['diameter'],
                'url' => $path,
            ];
        }

        return $items;
    }

    private function buildEntityBreadcrumb(array $global, string $langCode, array $entity, array $config, string $pageJsonDir = '', string $baseUrl = ''): array
    {
        $navSlug = (string) ($config['nav_slug'] ?? '');
        $listPageId = (string) ($config['list_page_id'] ?? $navSlug);
        $itemKey = (string) ($config['item_key'] ?? '');

        $inner = $itemKey !== '' ? ($entity[$itemKey] ?? []) : $entity;
        $name = (string) ($inner['name'] ?? $inner['title'] ?? $entity['slug'] ?? '');
        $slug = (string) ($entity['slug'] ?? '');

        $nav = $global['nav'][$langCode]['items'] ?? [];
        $homeTitle = 'Главная';
        $listTitle = '';
        $listHref = '/' . $navSlug;
        foreach ($nav as $navItem) {
            if (!is_array($navItem)) {
                continue;
            }
            $href = trim((string) ($navItem['href'] ?? ''), '/');
            if ($href === '' || $href === '/') {
                $homeTitle = (string) ($navItem['title'] ?? $homeTitle);
            }
            if ($href === $navSlug) {
                $listTitle = (string) ($navItem['title'] ?? '');
                $listHref = '/' . $href;
            }
        }

        // Fallback порядок для listTitle: nav.title → pages/{list}.json::title → pages/{list}.json::name → ucfirst(navSlug)
        if ($listTitle === '' && $pageJsonDir !== '' && $listPageId !== '') {
            $listPage = $this->dataLoader->loadPage($pageJsonDir, $listPageId, $baseUrl);
            if (is_array($listPage)) {
                $listTitle = (string) ($listPage['title'] ?? $listPage['name'] ?? '');
                // Защита от случая когда name === slug (например news.json::name === 'news')
                if ($listTitle === $navSlug) {
                    $listTitle = '';
                }
            }
        }
        if ($listTitle === '') {
            $listTitle = ucfirst($navSlug);
        }

        return [
            ['name' => $homeTitle, 'url' => '/'],
            ['name' => $listTitle, 'url' => $listHref],
            ['name' => $name, 'url' => rtrim($listHref, '/') . '/' . $slug],
        ];
    }

    /**
     * @param array<string,mixed> $pageData
     * @param array<string,mixed> $config
     */
    private function injectListItems(array &$pageData, string $jsonBaseDir, string $langCode, string $baseUrl, array $config): void
    {
        $navSlug = (string) ($config['nav_slug'] ?? '');
        $itemKey = (string) ($config['item_key'] ?? '');

        $slugs = [];
        $topLevelItems = $pageData['items'] ?? [];
        if (is_array($topLevelItems) && $topLevelItems !== []) {
            foreach ($topLevelItems as $item) {
                if (is_string($item) && $item !== '') {
                    $slugs[] = $item;
                } elseif (is_array($item) && isset($item['slug']) && is_string($item['slug']) && $item['slug'] !== '') {
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
                    || ($section['name'] ?? '') !== $navSlug
                    || !isset($section['data']['items'])
                    || !is_array($section['data']['items'])
                ) {
                    continue;
                }
                foreach ($section['data']['items'] as $item) {
                    if (is_string($item) && $item !== '') {
                        $slugs[] = $item;
                    } elseif (is_array($item) && isset($item['slug']) && is_string($item['slug']) && $item['slug'] !== '') {
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
        foreach ($slugs as $entitySlug) {
            $entity = $this->dataLoader->loadEntity($jsonBaseDir, $langCode, (string) $entitySlug, $baseUrl, $config);
            if ($entity === null) {
                continue;
            }
            $inner = $itemKey !== '' ? ($entity[$itemKey] ?? []) : $entity;
            $flat = [
                'slug' => $entity['slug'] ?? $entitySlug,
                'id' => $inner['id'] ?? null,
                'visible' => $entity['visible'] ?? true,
                'cover' => $inner['cover'] ?? ['src' => ''],
                'hex' => $inner['hex'] ?? '',
                'date' => $inner['date'] ?? '',
                'title' => $inner['title'] ?? $inner['name'] ?? '',
                'desc' => $inner['desc'] ?? $inner['lead'] ?? '',
                'href' => $inner['href'] ?? '',
            ];
            // Дополнительные entity-поля для фильтров/тегов/счётчиков на list-страницах
            foreach (['types', 'feature', 'tags', 'category', 'season'] as $extra) {
                if (isset($inner[$extra])) {
                    $flat[$extra] = $inner[$extra];
                }
            }
            $items[] = $flat;
        }

        // Инжект во все секции, где data.items пуст или отсутствует. Backward-compat:
        // секции с непустым data.items не перезаписываются (явный контент остаётся).
        // Это обслуживает паттерны hero+typeCounts / filter-chip / list-section одновременно
        // — не требуя дублирования items в JSON на каждой секции.
        foreach ($sections as $idx => $section) {
            if (!is_array($section) || !isset($section['data']) || !is_array($section['data'])) {
                continue;
            }
            $existing = $section['data']['items'] ?? null;
            if ($existing === null || (is_array($existing) && $existing === [])) {
                $sections[$idx]['data']['items'] = $items;
            }
        }
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>|null
     */
    private function extractFrameFromListPage(string $pageJsonDir, array $config, string $baseUrl): ?array
    {
        $listPageId = (string) ($config['list_page_id'] ?? '');
        if ($listPageId === '') {
            return null;
        }

        $listPage = $this->dataLoader->loadPage($pageJsonDir, $listPageId, $baseUrl);
        if ($listPage === null || !isset($listPage['sections']) || !is_array($listPage['sections'])) {
            return null;
        }

        foreach ($listPage['sections'] as $section) {
            if (is_array($section) && ($section['name'] ?? '') === 'frame' && isset($section['data'])) {
                return (array) $section['data'];
            }
        }

        return null;
    }

    /**
     * Разбирает сегменты адреса в пресет фильтра каталога.
     * Возвращает null, если сегменты не описывают фильтр — тогда работает обычная логика сущности.
     *
     * @param array<int, string> $routeParams
     * @param array<string, mixed> $collConfig
     * @return array<string, string>|null
     */
    /**
     * Человечный адрес для фильтра, пришедшего query-параметрами: /tires?season=winter → /tires/winter.
     * Возвращает null, если параметры не распознаны или путь уже содержит сегменты фильтра —
     * тогда страница обрабатывается как обычно.
     *
     * @param array<int,string>   $routeParams
     * @param array<string,mixed> $query
     */
    private function legacyFilterRedirect(string $pageId, array $routeParams, array $query): ?string
    {
        if ($routeParams !== [] || $query === []) {
            return null;
        }

        foreach ((array) ($this->settings['collections'] ?? []) as $collConfig) {
            $collConfig = (array) $collConfig;
            if ($pageId !== (string) ($collConfig['list_page_id'] ?? '')) {
                continue;
            }

            $filters = (array) ($collConfig['filters'] ?? []);
            if ($filters === []) {
                return null;
            }

            $navSlug = (string) ($collConfig['nav_slug'] ?? '');
            if ($navSlug === '') {
                return null;
            }

            $segments = [];

            $season = strtolower(trim((string) ($query['season'] ?? '')));
            if ($season !== '' && isset(((array) ($filters['season'] ?? []))[$season])) {
                $segments[] = $season;
            }

            // Размер собирается только целиком: по одной ширине человечного адреса не существует.
            $width = trim((string) ($query['width'] ?? ''));
            $profile = trim((string) ($query['profile'] ?? ''));
            $diameter = trim((string) ($query['diameter'] ?? ''));
            if ($width !== '' && $profile !== '' && $diameter !== '') {
                $size = strtolower($width . '-' . $profile . '-r' . $diameter);
                $pattern = (string) ($filters['size_pattern'] ?? '');
                if ($pattern !== '' && preg_match($pattern, $size) === 1) {
                    $segments[] = $size;
                }
            }

            if ($segments === []) {
                return null;
            }

            return '/' . $navSlug . '/' . implode('/', $segments);
        }

        return null;
    }

    private function parseFilterParams(array $routeParams, array $collConfig): ?array
    {
        $filters = (array) ($collConfig['filters'] ?? []);
        if ($filters === [] || $routeParams === [] || count($routeParams) > 2) {
            return null;
        }

        $seasons = (array) ($filters['season'] ?? []);
        $families = (array) ($filters['family'] ?? []);
        $sizePattern = (string) ($filters['size_pattern'] ?? '');
        $preset = [];

        foreach ($routeParams as $param) {
            $param = strtolower((string) $param);

            if (isset($seasons[$param]) && !isset($preset['season'])) {
                $preset['season'] = $param;
                continue;
            }

            if (isset($families[$param]) && !isset($preset['family'])) {
                $preset['family'] = $param;
                continue;
            }

            if ($sizePattern !== '' && !isset($preset['width']) && preg_match($sizePattern, $param, $m) === 1) {
                $size = ['width' => $m[1], 'profile' => $m[2], 'diameter' => $m[3]];
                $ranges = (array) ($filters['size_ranges'] ?? []);
                foreach ($ranges as $key => $range) {
                    $value = (int) ($size[$key] ?? 0);
                    if ($value < (int) ($range[0] ?? 0) || $value > (int) ($range[1] ?? 0)) {
                        return null;
                    }
                }
                $preset += $size;
                continue;
            }

            return null;
        }

        return $preset === [] ? null : $preset;
    }

    /**
     * @param array<string, mixed> $collections
     */
    private function isCollectionListPage(string $pageId, array $collections): bool
    {
        foreach ($collections as $collConfig) {
            if (is_array($collConfig) && (string) ($collConfig['list_page_id'] ?? '') === $pageId) {
                return true;
            }
        }

        return false;
    }

    private function dispatch(object $event): void
    {
        $this->dispatcher?->dispatch($event);
    }

}
