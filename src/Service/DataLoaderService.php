<?php

namespace App\Service;

use App\Support\JsonProcessor;

final class DataLoaderService
{
    public function loadGlobal(string $globalPath, string $baseUrl): array
    {
        return $this->loadJson($globalPath, $baseUrl) ?? [];
    }

    public function loadPage(string $pagesDir, string $pageId, string $baseUrl): ?array
    {
        $path = rtrim($pagesDir, '/') . '/' . $pageId . '.json';
        return $this->loadJson($path, $baseUrl);
    }

    public function loadSeo(string $jsonBaseDir, string $langCode, string $pageId, string $baseUrl): ?array
    {
        $seoPath = rtrim($jsonBaseDir, '/') . '/' . $langCode . '/seo/' . $pageId . '.json';
        return $this->loadJson($seoPath, $baseUrl);
    }

    /** @return array<int, string>|null */
    public function loadTireSlugs(string $jsonBaseDir, string $langCode): ?array
    {
        $path = rtrim($jsonBaseDir, '/') . '/' . $langCode . '/pages/tires.json';
        $data = $this->loadJson($path, '');
        return isset($data['items']) && is_array($data['items']) ? $data['items'] : null;
    }

    public function loadTire(string $jsonBaseDir, string $langCode, string $slug, string $baseUrl): ?array
    {
        $path = rtrim($jsonBaseDir, '/') . '/' . $langCode . '/tires/' . $slug . '.json';
        $data = $this->loadJson($path, $baseUrl);
        if ($data === null || empty($data['item']) || (isset($data['visible']) && $data['visible'] === false)) {
            return null;
        }
        return $data;
    }

    /** @return array<int, string>|null */
    public function loadNewsSlugs(string $jsonBaseDir, string $langCode): ?array
    {
        $path = rtrim($jsonBaseDir, '/') . '/' . $langCode . '/pages/news.json';
        $data = $this->loadJson($path, '');
        if (!is_array($data)) {
            return null;
        }

        $rawItems = [];
        if (isset($data['items']) && is_array($data['items'])) {
            $rawItems = $data['items'];
        } else {
            $sections = $data['sections'] ?? [];
            if (is_array($sections)) {
                foreach ($sections as $section) {
                    if (
                        is_array($section)
                        && ($section['name'] ?? '') === 'news'
                        && isset($section['data'])
                        && is_array($section['data'])
                        && isset($section['data']['items'])
                        && is_array($section['data']['items'])
                    ) {
                        $rawItems = $section['data']['items'];
                        break;
                    }
                }
            }
        }

        $slugs = [];
        foreach ($rawItems as $item) {
            if (is_string($item) && $item !== '') {
                $slugs[] = $item;
                continue;
            }
            if (is_array($item) && isset($item['slug']) && is_string($item['slug']) && $item['slug'] !== '') {
                $slugs[] = $item['slug'];
            }
        }

        return $slugs === [] ? null : array_values(array_unique($slugs));
    }

    public function loadNews(string $jsonBaseDir, string $langCode, string $slug, string $baseUrl): ?array
    {
        $path = rtrim($jsonBaseDir, '/') . '/' . $langCode . '/news/' . $slug . '.json';
        $data = $this->loadJson($path, $baseUrl);
        if ($data === null || empty($data['news']) || (isset($data['visible']) && $data['visible'] === false)) {
            return null;
        }
        $data['slug'] = $slug;
        return $data;
    }

    public function loadJson(string $path, string $baseUrl): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        JsonProcessor::processJsonPaths($data, $baseUrl);
        return $data;
    }
}
