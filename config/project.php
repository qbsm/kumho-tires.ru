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
        'contacts',
        'policy',
        'agree',
        'tires-list',
        'buy',
        'news',
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
    ],

    // Внешние интеграции (флаги включения)
    'integrations' => [
        'photoroom' => ['enabled' => true],
    ],
];
