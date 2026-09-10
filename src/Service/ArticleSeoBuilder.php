<?php

declare(strict_types=1);

namespace App\Service;

/**
 * SEO-builder для коллекции статей: Article JSON-LD и обложка статьи в og:image.
 *
 * Отличие от NewsSeoBuilder: тип Article вместо NewsArticle (материал справочный, а не новостной),
 * dateModified берётся отдельным полем `updated_iso` — статьи с таблицами из каталога переиздаются,
 * и дата правки для них значима сильнее даты первой публикации.
 */
final class ArticleSeoBuilder implements SeoBuilderInterface
{
    public function build(array $entity, string $baseUrl, string $langCode, array $config, array $global): array
    {
        $itemKey = (string) ($config['item_key'] ?? 'article');
        $inner = $itemKey !== '' ? (array) ($entity[$itemKey] ?? []) : $entity;

        $title = (string) ($inner['title'] ?? $entity['slug'] ?? '');
        $seoTitle = trim((string) ($inner['seo_title'] ?? ''));
        $metaTitle = $seoTitle !== '' ? $seoTitle : $title;
        $desc = (string) ($inner['desc'] ?? $inner['lead'] ?? '');
        $siteName = (string) ($global['name'] ?? $global['site_name'] ?? '');
        $origin = rtrim($baseUrl, '/');

        $cover = $inner['cover'] ?? '';
        if (is_array($cover)) {
            $cover = (string) ($cover['src'] ?? '');
        }
        $cover = (string) $cover;
        if ($cover === '') {
            $image = $origin . '/data/img/seo/og.jpg?v=3';
        } elseif (preg_match('~^https?://~i', $cover) === 1) {
            $image = $cover;
        } else {
            $image = $origin . '/' . ltrim($cover, '/');
        }
        // Telegram/WhatsApp не принимают webp — для превью берём JPEG-двойник <имя>-og.jpg.
        if (str_ends_with($image, '.webp')) {
            $image = substr($image, 0, -5) . '-og.jpg';
        }

        $meta = [
            ['name' => 'description', 'content' => $desc],
            ['property' => 'og:type', 'content' => (string) ($config['og_type'] ?? 'article')],
            ['property' => 'og:title', 'content' => $metaTitle],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:image', 'content' => $image],
        ];
        if ($siteName !== '') {
            $meta[] = ['property' => 'og:site_name', 'content' => $siteName];
        }

        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'description' => $desc,
            'image' => [$image],
            'inLanguage' => $langCode !== '' ? $langCode : 'ru',
        ];

        $dateIso = trim((string) ($inner['date_iso'] ?? ''));
        if ($dateIso !== '') {
            $article['datePublished'] = $dateIso;
            $updatedIso = trim((string) ($inner['updated_iso'] ?? ''));
            $article['dateModified'] = $updatedIso !== '' ? $updatedIso : $dateIso;
        }

        if ($siteName !== '') {
            $article['publisher'] = [
                '@type' => 'Organization',
                'name' => $siteName,
                'url' => $origin . '/',
            ];
            $article['author'] = ['@type' => 'Organization', 'name' => $siteName];
        }

        $slug = (string) ($entity['slug'] ?? '');
        $navSlug = (string) ($config['nav_slug'] ?? 'articles');
        if ($slug !== '') {
            $article['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id' => $origin . '/' . $navSlug . '/' . $slug,
            ];
        }

        $jsonLd = json_encode($article, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'title' => $metaTitle,
            'meta' => $meta,
            'json_ld' => $jsonLd !== false ? $jsonLd : null,
            // FAQPage не строим: блок faq на странице рендерится штатным components/accordion.twig,
            // который сам отдаёт и микроданные, и JSON-LD. Вторая разметка была бы дублем.
            'json_ld_faq' => null,
        ];
    }
}
