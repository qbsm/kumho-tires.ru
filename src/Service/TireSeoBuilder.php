<?php

declare(strict_types=1);

namespace App\Service;

/**
 * SEO-builder для коллекции шин: усиленный title + Product JSON-LD (Schema.org).
 *
 * Title и Product-разметка строятся из данных модели (item.name/code, season, sizes).
 * Цены/рейтинги НЕ выдумываются — Offer/AggregateRating добавляются только при наличии реальных данных.
 */
final class TireSeoBuilder implements SeoBuilderInterface
{
    public function build(array $entity, string $baseUrl, string $langCode, array $config, array $global): array
    {
        $itemKey = (string) ($config['item_key'] ?? 'item');
        $inner = $itemKey !== '' ? (array) ($entity[$itemKey] ?? []) : $entity;

        $name = (string) ($inner['name'] ?? $inner['title'] ?? $entity['slug'] ?? '');
        $code = (string) ($inner['code'] ?? '');
        $season = (string) ($entity['season'] ?? '');
        $descShort = (string) ($entity['desc']['short'] ?? '');
        $descFull = (string) ($entity['desc']['full'] ?? '');
        $desc = $descShort !== '' ? $descShort : $descFull;
        $siteName = (string) ($global['name'] ?? $global['site_name'] ?? '');
        $origin = rtrim($baseUrl, '/');

        // Диапазон посадочных диаметров из таблицы размеров (для title и разметки).
        $diameters = [];
        foreach ((array) ($entity['sizes'] ?? []) as $size) {
            if (is_array($size) && isset($size['diameter']) && (int) $size['diameter'] > 0) {
                $diameters[] = (int) $size['diameter'];
            }
        }
        $sizeStr = '';
        if ($diameters !== []) {
            $min = min($diameters);
            $max = max($diameters);
            $sizeStr = $min === $max ? ('R' . $min) : ('R' . $min . '–R' . $max);
        }

        // Усиленный SEO-title (на h1 не влияет — он в шаблоне).
        $titleParts = [];
        if (stripos($name, 'шина') === false) {
            $titleParts[] = 'Шина';
        }
        $titleParts[] = $name;
        if ($sizeStr !== '') {
            $titleParts[] = $sizeStr;
        }
        $title = trim(implode(' ', $titleParts));
        $title = $title !== '' ? ($title . ' — характеристики и размеры') : $name;

        $imgSrc = '';
        foreach (['30deg', 'front', 'side', 'back'] as $k) {
            if (isset($entity['images'][$k]['src']) && is_string($entity['images'][$k]['src'])) {
                $imgSrc = (string) $entity['images'][$k]['src'];
                break;
            }
        }
        $imageUrl = $imgSrc !== '' ? $origin . '/' . ltrim($imgSrc, '/') : '';

        // og:image — рендер конкретной модели, а не общая картинка сайта: в выдаче и репостах
        // каждая шина выглядит собой.
        $ogImage = $imageUrl !== '' ? $imageUrl : $origin . '/data/img/seo/og.webp?v=2';
        $meta = [
            ['name' => 'description', 'content' => $desc],
            ['property' => 'og:type', 'content' => (string) ($config['og_type'] ?? 'website')],
            ['property' => 'og:title', 'content' => $title],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:image', 'content' => $ogImage],
        ];
        if ($siteName !== '') {
            $meta[] = ['property' => 'og:site_name', 'content' => $siteName];
        }

        // Product JSON-LD. Kumho — бренд шин («Кумхо» по-русски), Kumho Tire — компания-производитель.
        $product = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $name,
            'description' => $desc,
            'brand' => ['@type' => 'Brand', 'name' => 'Kumho', 'alternateName' => 'Кумхо'],
            'manufacturer' => ['@type' => 'Organization', 'name' => 'Kumho Tire'],
            'category' => 'Автомобильные шины',
        ];
        if ($code !== '') {
            $product['mpn'] = $code;
        }
        $slug = (string) ($entity['slug'] ?? '');
        $navSlug = (string) ($config['nav_slug'] ?? 'tires');
        if ($slug !== '') {
            $product['url'] = $origin . '/' . $navSlug . '/' . $slug;
        }
        if ($imageUrl !== '') {
            $product['image'] = $imageUrl;
        }
        $props = [];
        if ($season !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Сезонность', 'value' => $season];
        }
        if ($sizeStr !== '') {
            $props[] = ['@type' => 'PropertyValue', 'name' => 'Посадочный диаметр', 'value' => $sizeStr];
        }
        if ($props !== []) {
            $product['additionalProperty'] = $props;
        }

        $jsonLd = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'title' => $title,
            'meta' => $meta,
            'json_ld' => $jsonLd !== false ? $jsonLd : null,
            'json_ld_faq' => null,
        ];
    }
}
