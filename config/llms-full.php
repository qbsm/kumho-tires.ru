<?php

declare(strict_types=1);

/**
 * Конфигурация генератора llms-full.txt (GEO).
 * Описывает коллекции контента: откуда брать список slug'ов и как форматировать каждый элемент.
 * Для универсального ядра — подставьте свои коллекции под тип проекта (каталог, рестораны, события и т.д.).
 *
 * @return array{title: string, intro: string, collections: array<int, array{list_path: string, list_key: string, item_dir: string, name_key: string, desc_key?: string, visible_key?: string, fields: array<int, array{label: string, key: string}>}>}
 */
return [
    'title' => 'Kumho Tire',
    'intro' => 'kumho-tires.ru — официальный сайт дистрибьютора шин Kumho в России. '
        . 'Каталог летних и всесезонных шин Kumho для легковых автомобилей, кроссоверов, внедорожников, пикапов '
        . 'и лёгкого коммерческого транспорта — с характеристиками и таблицами доступных размеров по каждой модели. '
        . 'Линейки: ECSTA, Road Venture, CRUGEN, SOLUS, Ecowing, PorTran. '
        . 'Действует программа «Расширенная гарантия Kumho»: дополнительный год к гарантии производителя на замену или ремонт шины '
        . 'при регистрации чека на сайте (на летние легковые модели Kumho, покупки с 01.03.2026 по 31.08.2026). '
        . 'Купить шины Kumho можно у официальных дилеров в 46 городах России (в том числе Екатеринбург, Казань, Воронеж, Волгоград, Барнаул) — '
        . 'раздел «Где купить»: /buy/. Контакты — /contacts/.',
    'collections' => [
        [
            'list_path' => '{lang}/pages/tires.json',
            'list_key' => 'items',
            'item_dir' => '{lang}/tires',
            'name_key' => 'item.name',
            'desc_key' => 'desc.short',
            'visible_key' => 'visible',
            'fields' => [
                ['label' => 'Код', 'key' => 'item.code'],
                ['label' => 'Серия', 'key' => 'item.series'],
                ['label' => 'Сезонность', 'key' => 'season'],
                ['label' => 'Доступные размеры', 'key' => 'sizes'],
            ],
        ],
    ],
];
