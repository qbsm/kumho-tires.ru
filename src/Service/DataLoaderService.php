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
