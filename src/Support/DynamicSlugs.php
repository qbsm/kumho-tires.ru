<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Допустимые подпути страницы-конструктора: /buy/<город>, /news/<новость> и т.п.
 *
 * Один источник для sitemap.xml и роутинга: список в карте сайта и список адресов,
 * которые отдают 200, обязаны совпадать — иначе либо страница есть, но её нет в карте,
 * либо любой мусорный слаг открывается как страница и плодит дубли.
 */
final class DynamicSlugs
{
    /**
     * @param array<string, mixed> $config описание подпутей (sitemap_dynamic_pages[pageId])
     * @return array<int, string>
     */
    public static function list(string $jsonBaseDir, string $lang, array $config): array
    {
        $dataPage = (string) ($config['data_page'] ?? '');
        $listKey = (string) ($config['list_key'] ?? '');
        $valueKey = (string) ($config['value_key'] ?? '');
        $sluggerKey = (string) ($config['slugger'] ?? 'city');
        $entityDir = (string) ($config['entity_dir'] ?? '');

        if ($jsonBaseDir === '' || $dataPage === '' || $listKey === '') {
            return [];
        }

        $file = $jsonBaseDir . '/' . $lang . '/pages/' . $dataPage . '.json';
        $items = Json::loadKey($file, $listKey);
        if ($items === null) {
            // Страницы-конструкторы держат список внутри секции (sections[].data[listKey]),
            // а не в корне JSON — например, новости.
            $items = self::listFromSections($file, $listKey);
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
            $slug = self::slugify($value, $sluggerKey);
            if ($slug === '' || in_array($slug, $slugs, true)) {
                continue;
            }
            if ($entityDir !== '' && !self::isEntityVisible($jsonBaseDir, $lang, $entityDir, $slug)) {
                continue;
            }
            $slugs[] = $slug;
        }
        sort($slugs);

        return $slugs;
    }

    /**
     * @return array<int, mixed>|null
     */
    public static function listFromSections(string $file, string $listKey): ?array
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
     * Сущность доступна только если её JSON существует и не скрыт (visible !== false) —
     * та же логика, что в DataLoaderService::loadEntity.
     */
    public static function isEntityVisible(string $jsonBaseDir, string $lang, string $entityDir, string $slug): bool
    {
        $data = Json::load($jsonBaseDir . '/' . $lang . '/' . $entityDir . '/' . $slug . '.json');
        if ($data === null) {
            return false;
        }

        return !(isset($data['visible']) && $data['visible'] === false);
    }

    public static function slugify(string $value, string $sluggerKey): string
    {
        return match ($sluggerKey) {
            'identity', 'slug' => trim($value),
            default => CitySlugger::slug($value),
        };
    }
}
