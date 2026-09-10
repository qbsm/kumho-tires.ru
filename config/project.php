<?php

// Проектная конфигурация — специфичная для текущего заказчика / deployment'а.
// Для нового заказчика: скопировать project.php.dist -> project.php и адаптировать.

return [
    // slug в URL => page_id (файл data/json/{lang}/pages/{page_id}.json)
    'route_map' => [
        'tires' => 'tires-list',
        'news' => 'news',
        'articles' => 'articles',
    ],

    // Параметризация коллекций: каждая коллекция описывается полностью через конфиг.
    // Добавление/удаление коллекции — только здесь, 0 правок PHP.
    'collections' => [
        'tires' => [
            'nav_slug'     => 'tires',
            'list_page_id' => 'tires-list',
            'template'     => 'pages/tire.twig',
            'item_key'     => 'item',
            'data_dir'     => 'tires',
            'slugs_source' => 'items',
            'og_type'      => 'website',
            'extras_key'   => 'tire',
            // Человечные адреса фильтра: /tires/summer, /tires/205-55-r16, /tires/summer/205-55-r16.
            // Значения сезонов совпадают с токенами фильтра каталога.
            'filters'      => [
                'season' => [
                    'summer'    => ['label' => 'Летние', 'genitive' => 'летних', 'h1' => 'Летние шины Kumho'],
                    'allseason' => ['label' => 'Всесезонные', 'genitive' => 'всесезонных', 'h1' => 'Всесезонные шины Kumho'],
                    'winter'    => ['label' => 'Зимние', 'genitive' => 'зимних', 'h1' => 'Зимние шины Kumho'],
                    'studded'   => ['label' => 'Шипованные', 'genitive' => 'шипованных', 'h1' => 'Шипованные шины Kumho'],
                    'friction'  => ['label' => 'Нешипованные', 'genitive' => 'нешипованных', 'h1' => 'Нешипованные зимние шины Kumho'],
                ],
                // Популярные типоразмеры, открытые для индексации: пересечение спроса из Вордстата
                // («шины kumho <размер>», Россия, 28.07–26.08.2026) с наличием в каталоге.
                // Остальные сотни комбинаций остаются под noindex, follow — они дублируют друг друга.
                'indexable_sizes' => [
                    '185-65-r15',
                    '205-55-r16',
                    '215-65-r16',
                    '225-55-r18',
                    '205-65-r16',
                    '195-65-r15',
                    '215-60-r17',
                    '225-60-r18',
                    '225-65-r17',
                    '225-60-r17',
                    '225-45-r18',
                    '235-55-r18',
                    '225-55-r19',
                    '235-55-r19',
                    '235-45-r18',
                    '205-60-r16',
                    '215-55-r18',
                    '185-60-r15',
                    '175-65-r14',
                    '215-65-r17',
                    '215-55-r17',
                    '235-65-r17',
                    '255-50-r20',
                    '265-60-r18',
                    '235-55-r17',
                    '185-60-r14',
                    '225-45-r17',
                    '205-50-r17',
                    '235-60-r18',
                    '225-55-r17',
                ],

                // Линейки моделей: отдельные посадочные под запросы вида «шины kumho wintercraft»
                'family' => [
                    'wintercraft'  => ['label' => 'WinterCraft', 'h1' => 'Шины Kumho WinterCraft'],
                    'ecsta'        => ['label' => 'Ecsta', 'h1' => 'Шины Kumho Ecsta'],
                    'ecowing'      => ['label' => 'Ecowing', 'h1' => 'Шины Kumho Ecowing'],
                    'crugen'       => ['label' => 'Crugen', 'h1' => 'Шины Kumho Crugen'],
                    'solus'        => ['label' => 'Solus', 'h1' => 'Шины Kumho Solus'],
                    'road-venture' => ['label' => 'Road Venture', 'h1' => 'Шины Kumho Road Venture'],
                    'portran'      => ['label' => 'PorTran', 'h1' => 'Шины Kumho PorTran для коммерческого транспорта'],
                ],
                'size_pattern' => '/^(\\d{3})-(\\d{2,3})-r(\\d{2})$/i',
                // Границы реального каталога: за ними адрес — мусор, отдаём 404, а не пустой фильтр
                'size_ranges'  => [
                    'width'    => [125, 325],
                    'profile'  => [25, 85],
                    'diameter' => [12, 22],
                ],
            ],
        ],
        'news' => [
            'nav_slug'     => 'news',
            'list_page_id' => 'news',
            'template'     => 'pages/news.twig',
            'item_key'     => 'news',
            'data_dir'     => 'news',
            'slugs_source' => 'items',
            'og_type'      => 'article',
            'extras_key'   => 'news',
        ],
        'articles' => [
            'nav_slug'     => 'articles',
            'list_page_id' => 'articles',
            'template'     => 'pages/article.twig',
            'item_key'     => 'article',
            'data_dir'     => 'articles',
            'slugs_source' => 'items',
            'og_type'      => 'article',
            'extras_key'   => 'article',
        ],
    ],

    // page_id страниц для sitemap.xml (без 404)
    'sitemap_pages' => [
        'index',
        'about',
        'brand',
        'contacts',
        'policy',
        'cookies-policy',
        'tires-list',
        'podbor',
        'buy',
        'news',
        'articles',
        'warranty',
    ],

    // Динамические подпути в sitemap.xml: page_id => источник данных и стратегия slug.
    // Для /buy раскрываем {lang}/pages/dealers.json → items[].city → /buy/<city-slug>/.
    'sitemap_dynamic_pages' => [
        'buy' => [
            'data_page' => 'dealers',
            'list_key' => 'items',
            'value_key' => 'city',
            'slugger' => 'city',
        ],
        // Новости: список лежит внутри секции news в pages/news.json, slug — прямо в элементе.
        'news' => [
            'data_page' => 'news',
            'list_key' => 'items',
            'value_key' => 'slug',
            'slugger' => 'identity',
            'entity_dir' => 'news',
        ],

        // Статьи: порядок и состав раздела — ключ items верхнего уровня в pages/articles.json.
        'articles' => [
            'data_page' => 'articles',
            'list_key' => 'items',
            'value_key' => '',
            'slugger' => 'identity',
            'entity_dir' => 'articles',
        ],

        'tires-list' => [
            'data_page' => 'tires',
            'list_key' => 'items',
            'value_key' => '',
            'slugger' => 'identity',
            // Директория сущностей: slug исключается из sitemap, если {lang}/tires/<slug>.json скрыт (visible:false)
            'entity_dir' => 'tires',
        ],
    ],

    // Дополнительные статические адреса в sitemap.xml (человечные страницы фильтра каталога).
    'sitemap_extra_paths' => [
        'tires/wintercraft',
        'tires/ecsta',
        'tires/ecowing',
        'tires/crugen',
        'tires/solus',
        'tires/road-venture',
        'tires/portran',
        'tires/friction',
        'tires/summer',
        'tires/allseason',
        'tires/winter',
        'tires/studded',
        'tires/185-65-r15',
        'tires/205-55-r16',
        'tires/215-65-r16',
        'tires/225-55-r18',
        'tires/205-65-r16',
        'tires/195-65-r15',
        'tires/215-60-r17',
        'tires/225-60-r18',
        'tires/225-65-r17',
        'tires/225-60-r17',
        'tires/225-45-r18',
        'tires/235-55-r18',
        'tires/225-55-r19',
        'tires/235-55-r19',
        'tires/235-45-r18',
        'tires/205-60-r16',
        'tires/215-55-r18',
        'tires/185-60-r15',
        'tires/175-65-r14',
        'tires/215-65-r17',
        'tires/215-55-r17',
        'tires/235-65-r17',
        'tires/255-50-r20',
        'tires/265-60-r18',
        'tires/235-55-r17',
        'tires/185-60-r14',
        'tires/225-45-r17',
        'tires/205-50-r17',
        'tires/235-60-r18',
        'tires/225-55-r17',
    ],

    // Внешние интеграции (флаги включения)
    'integrations' => [

    ],
];
