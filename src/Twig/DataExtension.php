<?php

namespace App\Twig;

use App\Support\JsonProcessor;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataExtension extends AbstractExtension
{
    private string $baseDir;
    private string $baseUrl;
    /** @var array<string, array<string,mixed>|null> */
    private array $cache = [];
    /** @var array<string, array{width: int, height: int}>|null */
    private ?array $imageDimensionsManifest = null;

    public function __construct(string $baseDir, string $baseUrl)
    {
        $this->baseDir = rtrim($baseDir, '/');
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('load_json', [$this, 'loadJson']),
            new TwigFunction('image_dimensions', [$this, 'getImageDimensions']),
        ];
    }

    /**
     * Возвращает { width, height } для пути из манифеста (tools/build/images.js).
     *
     * @return array{width: int, height: int}|null
     */
    /**
     * Путь в манифесте: относительно data/img (например intro/cover.jpg или restaurants/.../1.jpg).
     * В шаблон может приходить с префиксом data/img/ — он отрезается при поиске.
     */
    public function getImageDimensions(string $path): ?array
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#^data/img/#', '', $path);
        if ($path === '') {
            return null;
        }
        if ($this->imageDimensionsManifest === null) {
            $manifestPath = $this->baseDir . '/data/img/image-dimensions.json';
            if (!is_file($manifestPath)) {
                $this->imageDimensionsManifest = [];
                return null;
            }
            $content = @file_get_contents($manifestPath);
            $data = $content !== false ? json_decode($content, true) : null;
            $this->imageDimensionsManifest = is_array($data) ? $data : [];
        }
        $entry = $this->imageDimensionsManifest[$path] ?? null;
        if (!is_array($entry) || !isset($entry['width'], $entry['height'])) {
            return null;
        }
        return ['width' => (int) $entry['width'], 'height' => (int) $entry['height']];
    }

    public function loadJson(string $relativePath): ?array
    {
        $relativePath = ltrim($relativePath, '/');

        if (array_key_exists($relativePath, $this->cache)) {
            return $this->cache[$relativePath];
        }

        $fullPath = $this->baseDir . '/' . $relativePath;
        if (!is_file($fullPath)) {
            $this->cache[$relativePath] = null;
            return null;
        }

        $content = @file_get_contents($fullPath);
        if ($content === false) {
            $this->cache[$relativePath] = null;
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->cache[$relativePath] = null;
            return null;
        }

        JsonProcessor::processJsonPaths($data, $this->baseUrl);
        $this->cache[$relativePath] = $data;

        return $data;
    }
}
