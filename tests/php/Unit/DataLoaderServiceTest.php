<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\DataLoaderService;
use PHPUnit\Framework\TestCase;

final class DataLoaderServiceTest extends TestCase
{
    private DataLoaderService $service;

    protected function setUp(): void
    {
        $this->service = new DataLoaderService();
    }

    public function testLoadJsonReturnsNullForMissingFile(): void
    {
        $result = $this->service->loadJson(__DIR__ . '/../fixtures/nonexistent.json', 'https://example.com/');
        self::assertNull($result);
    }

    public function testLoadJsonReturnsNullForInvalidJson(): void
    {
        $path = __DIR__ . '/../fixtures/invalid.json';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, '{ invalid }');
        try {
            $result = $this->service->loadJson($path, 'https://example.com/');
            self::assertNull($result);
        } finally {
            @unlink($path);
        }
    }

    public function testLoadJsonReturnsArrayAndProcessesPaths(): void
    {
        $path = $this->getGlobalJsonPath();
        if (!is_file($path)) {
            self::markTestSkipped('data/json/global.json not found');
        }
        $result = $this->service->loadJson($path, 'https://example.com/');
        self::assertIsArray($result);
        self::assertArrayHasKey('lang', $result);
    }

    public function testLoadPageReturnsNullForMissingPage(): void
    {
        $pagesDir = dirname(__DIR__, 2) . '/data/json/ru/pages';
        $result = $this->service->loadPage($pagesDir, 'nonexistent-page-id', 'https://example.com/');
        self::assertNull($result);
    }

    public function testLoadPageReturnsDataForExistingPage(): void
    {
        $pagesDir = dirname(__DIR__, 2) . '/data/json/ru/pages';
        if (!is_dir($pagesDir)) {
            self::markTestSkipped('data/json/ru/pages not found');
        }
        $result = $this->service->loadPage($pagesDir, 'index', 'https://example.com/');
        if ($result === null) {
            self::markTestSkipped('data/json/ru/pages/index.json not found');
        }
        self::assertIsArray($result);
        self::assertArrayHasKey('sections', $result);
    }

    public function testLoadGlobalReturnsEmptyArrayWhenFileMissing(): void
    {
        $result = $this->service->loadGlobal(__DIR__ . '/../fixtures/nonexistent.json', 'https://example.com/');
        self::assertSame([], $result);
    }

    private function getGlobalJsonPath(): string
    {
        return dirname(__DIR__, 2) . '/data/json/global.json';
    }
}
