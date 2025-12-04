<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class RangesTest extends TestCase
{
    private Vision $vision;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_ranges_' . uniqid();
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

    // Basic range
    public function testBasicRange(): void
    {
        $this->createTemplate('test.html', "{% for i in 1..5 %}{{ i }},{% endfor %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('1,', $result);
        $this->assertStringContainsString('5,', $result);
        // Should contain 1,2,3,4,5,
        $this->assertStringContainsString('2,', $result);
        $this->assertStringContainsString('3,', $result);
    }

    public function testRangeZeroTo(): void
    {
        $this->createTemplate('test.html', "{% for i in 0..3 %}{{ i }}{% endfor %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('0', $result);
        $this->assertStringContainsString('3', $result);
    }

    public function testRangeNegative(): void
    {
        $this->createTemplate('test.html', "{% for i in -2..2 %}{{ i }},{% endfor %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('-2,', $result);
        $this->assertStringContainsString('2,', $result);
    }

    // Range with variables
    public function testRangeWithVariables(): void
    {
        $this->createTemplate('test.html', "{% for i in start..end %}{{ i }},{% endfor %}");
        $result = $this->vision->render('test.html', ['start' => 2, 'end' => 5]);
        $this->assertStringContainsString('2,', $result);
        $this->assertStringContainsString('5,', $result);
    }

    // Range with step
    public function testRangeWithStep(): void
    {
        $this->createTemplate('test.html', "{% for i in 0..10..2 %}{{ i }},{% endfor %}");
        $result = $this->vision->render('test.html', []);
        // Should be 0, 2, 4, 6, 8, 10
        $this->assertStringContainsString('0,', $result);
        $this->assertStringContainsString('2,', $result);
        $this->assertStringContainsString('4,', $result);
        $this->assertStringNotContainsString('1,', $result);
    }

    // Range in if condition
    public function testRangeInCondition(): void
    {
        $this->createTemplate('test.html', "{% if 5 in 1..10 %}yes{% else %}no{% endif %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
    }

    // Range not in condition
    public function testRangeNotInCondition(): void
    {
        $this->createTemplate('test.html', "{% if 15 in 1..10 %}yes{% else %}no{% endif %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('no', $result);
    }

    // Range with letters (a..z not supported in MVP)
    public function testRangeWithNumbers(): void
    {
        $this->createTemplate('test.html', "{% for i in 10..15 %}{{ i }},{% endfor %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('10,', $result);
        $this->assertStringContainsString('15,', $result);
    }

    // Complex: range in array context
    public function testRangeAsArray(): void
    {
        $this->createTemplate('test.html', "{{ range | length }}");
        $result = $this->vision->render('test.html', ['range' => range(1, 5)]);
        $this->assertStringContainsString('5', $result);
    }

    // Loop variable with range
    public function testRangeWithLoopVariable(): void
    {
        $this->createTemplate('test.html', "{% for i in 1..3 %}{{ loop.index }}{% endfor %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('3', $result);
    }
}
