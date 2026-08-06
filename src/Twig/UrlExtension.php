<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UrlExtension extends AbstractExtension
{
    private string $baseUrl;
    private string $imgVersion;

    public function __construct(string $baseUrl, string $imgVersion = '')
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->imgVersion = $imgVersion;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('url', [$this, 'generateUrl']),
        ];
    }

    public function generateUrl(?string $path = ''): string
    {
        if ($path === null) {
            return '#';
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '#')
            || str_starts_with($path, 'tel:')
            || str_starts_with($path, 'mailto:')
        ) {
            return $path;
        }

        // Канонический адрес — без хвостового слеша (его срезает TrailingSlashMiddleware).
        // Пока url() дописывал слеш, каждая внутренняя ссылка сайта шла через 301.
        $trimmedPath = ltrim($path, '/');
        if ($trimmedPath !== '' && strpos((string) basename($trimmedPath), '.') === false) {
            $trimmedPath = rtrim($trimmedPath, '/');
        }

        // Статика (data/, assets/) всегда от корня документа (public/), иначе на /ru/ картинки 404
        if ($trimmedPath !== '' && (str_starts_with($trimmedPath, 'data/') || str_starts_with($trimmedPath, 'assets/'))) {
            return $this->withImgVersion('/' . $trimmedPath, $trimmedPath);
        }

        return $this->withImgVersion($this->baseUrl . $trimmedPath, $trimmedPath);
    }

    // Cache-busting для изображений: статика отдаётся с max-age=1y immutable, поэтому при замене
    // файла под тем же именем посетитель видит старый кадр. IMG_CACHE_VERSION бампится в .env.
    private function withImgVersion(string $url, string $path): string
    {
        if ($this->imgVersion !== '' && preg_match('/\.(webp|avif|jpe?g|png|svg|gif|ico)$/i', $path)) {
            return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $this->imgVersion;
        }

        return $url;
    }
}
