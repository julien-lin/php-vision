<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class AdvancedFiltersTest extends TestCase
{
    private Vision $vision;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_adv_' . uniqid();
        @mkdir($this->tempDir, 0777, true);
        $this->vision = new Vision($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->deleteDir($path) : @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function createTemplate(string $name, string $content): void
    {
        $path = $this->tempDir . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
    }

    public function testFirstFilterArray(): void
    {
        $this->createTemplate('test.html', "{{ items | first | join }}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4, 5]]);
        $this->assertStringContainsString('1', $result);
    }

    public function testFirstFilterWithCount(): void
    {
        $this->createTemplate('test.html', "{{ items | first:2 | join:, }}");
        $result = $this->vision->render('test.html', ['items' => [10, 20, 30, 40]]);
        $this->assertStringContainsString('10', $result);
        $this->assertStringContainsString('20', $result);
    }

    public function testLastFilterArray(): void
    {
        $this->createTemplate('test.html', "{{ items | last | join }}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4, 5]]);
        $this->assertStringContainsString('5', $result);
    }

    public function testLastFilterWithCount(): void
    {
        $this->createTemplate('test.html', "{{ items | last:2 | join:, }}");
        $result = $this->vision->render('test.html', ['items' => [10, 20, 30, 40, 50]]);
        $this->assertStringContainsString('40', $result);
        $this->assertStringContainsString('50', $result);
    }

    public function testSliceFilterArray(): void
    {
        $this->createTemplate('test.html', "{{ items | slice:1,2 | join:, }}");
        $result = $this->vision->render('test.html', ['items' => ['a', 'b', 'c', 'd']]);
        $this->assertStringContainsString('b', $result);
        $this->assertStringContainsString('c', $result);
    }

    public function testJoinFilter(): void
    {
        $this->createTemplate('test.html', "{{ items | join }}");
        $result = $this->vision->render('test.html', ['items' => ['a', 'b', 'c']]);
        $this->assertEquals('abc', $result);
    }

    public function testJoinFilterSeparator(): void
    {
        $this->createTemplate('test.html', "{{ items | join:- }}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4, 5]]);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('5', $result);
    }

    public function testSortFilter(): void
    {
        $this->createTemplate('test.html', "{{ items | sort | join:, }}");
        $result = $this->vision->render('test.html', ['items' => [3, 1, 4, 1, 5, 9, 2, 6]]);
        $this->assertIsString($result);
    }

    public function testReverseFilterArray(): void
    {
        $this->createTemplate('test.html', "{{ items | reverse | join:- }}");
        $result = $this->vision->render('test.html', ['items' => ['a', 'b', 'c', 'd']]);
        $this->assertStringContainsString('d', $result);
        $this->assertStringContainsString('a', $result);
    }

    public function testReverseFilterString(): void
    {
        $this->createTemplate('test.html', "{{ text | reverse }}");
        $result = $this->vision->render('test.html', ['text' => 'Hello']);
        $this->assertEquals('olleH', $result);
    }

    public function testChainedFilters(): void
    {
        $this->createTemplate('test.html', "{{ items | first:3 | reverse | join:, }}");
        $result = $this->vision->render('test.html', ['items' => ['a', 'b', 'c', 'd', 'e']]);
        $this->assertIsString($result);
    }

    public function testEmptyArrayFilters(): void
    {
        $this->createTemplate('test.html', "{{ items | first }}");
        $result = $this->vision->render('test.html', ['items' => []]);
        $this->assertIsString($result);
    }

    public function testNullValueFilters(): void
    {
        $this->createTemplate('test.html', "{{ value | reverse }}");
        $result = $this->vision->render('test.html', ['value' => null]);
        $this->assertIsString($result);
    }
}
