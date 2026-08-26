<?php

// Проектная конфигурация — специфичная для текущего заказчика / deployment'а.
// Для нового заказчика: скопировать project.php.dist -> project.php и адаптировать.

return [
    // slug в URL => page_id (файл data/json/{lang}/pages/{page_id}.json)
    'route_map' => [
        'tires' => 'tires-list',
        'news' => 'news',
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
    ],

    // page_id страниц для sitemap.xml (без 404)
    'sitemap_pages' => [
        'index',
        'about',
        'contacts',
        'policy',
        'cookies-policy',
        'tires-list',
        'podbor',
        'buy',
        'news',
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
        'tires/friction',
        'tires/summer',
        'tires/allseason',
        'tires/winter',
        'tires/studded',
    ],

    // Внешние интеграции (флаги включения)
    'integrations' => [

    ],
];
