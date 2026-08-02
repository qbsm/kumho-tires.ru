<?php

declare(strict_types=1);

namespace App\Service;

/**
 * SEO-builder для коллекции новостей: NewsArticle JSON-LD + обложка новости в og:image.
 *
 * До этого новости шли через DefaultSeoBuilder: без микроразметки и с общей og-картинкой сайта,
 * из-за чего в выдаче и репостах каждая новость выглядела одинаково.
 * datePublished берётся из `date_iso` (ISO-8601); при его отсутствии поле не выдумывается.
 */
final class NewsSeoBuilder implements SeoBuilderInterface
{
    public function build(array $entity, string $baseUrl, string $langCode, array $config, array $global): array
    {
        $itemKey = (string) ($config['item_key'] ?? 'news');
        $inner = $itemKey !== '' ? (array) ($entity[$itemKey] ?? []) : $entity;

        $title = (string) ($inner['title'] ?? $entity['slug'] ?? '');
        $desc = (string) ($inner['desc'] ?? $inner['lead'] ?? '');
        $siteName = (string) ($global['name'] ?? $global['site_name'] ?? '');
        $origin = rtrim($baseUrl, '/');

        $cover = $inner['cover'] ?? '';
        if (is_array($cover)) {
            $cover = (string) ($cover['src'] ?? '');
        }
        $cover = (string) $cover;
        // cover может прийти как относительный raw-path или уже абсолютным URL — второй раз домен не клеим.
        if ($cover === '') {
            $image = $origin . '/data/img/seo/og.jpg?v=3';
        } elseif (preg_match('~^https?://~i', $cover) === 1) {
            $image = $cover;
        } else {
            $image = $origin . '/' . ltrim($cover, '/');
        }

        $meta = [
            ['name' => 'description', 'content' => $desc],
            ['property' => 'og:type', 'content' => (string) ($config['og_type'] ?? 'article')],
            ['property' => 'og:title', 'content' => $title],
            ['property' => 'og:description', 'content' => $desc],
            ['property' => 'og:image', 'content' => $image],
        ];
        if ($siteName !== '') {
            $meta[] = ['property' => 'og:site_name', 'content' => $siteName];
        }

        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $title,
            'description' => $desc,
            'image' => [$image],
            'inLanguage' => $langCode !== '' ? $langCode : 'ru',
        ];

        $dateIso = trim((string) ($inner['date_iso'] ?? ''));
        if ($dateIso !== '') {
            $article['datePublished'] = $dateIso;
            $article['dateModified'] = $dateIso;
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
        $navSlug = (string) ($config['nav_slug'] ?? 'news');
        if ($slug !== '') {
            $article['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id' => $origin . '/' . $navSlug . '/' . $slug,
            ];
        }

        $jsonLd = json_encode($article, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'title' => $title,
            'meta' => $meta,
            'json_ld' => $jsonLd !== false ? $jsonLd : null,
            'json_ld_faq' => null,
        ];
    }
}
