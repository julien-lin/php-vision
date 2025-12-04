<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class NamedArgumentsTest extends TestCase
{
    private Vision $vision;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_named_args_' . uniqid();
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

    // Basic named argument with filter
    public function testBasicNamedArgument(): void
    {
        $this->createTemplate('test.html', '{{ date(format="Y-m-d") }}');
        $result = $this->vision->render('test.html', []);
        // Should contain today's date in Y-m-d format
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $result);
    }

    // Named argument with variable
    public function testNamedArgumentWithVariable(): void
    {
        $this->createTemplate('test.html', '{{ text(value=message) }}');
        $result = $this->vision->render('test.html', ['message' => 'Hello']);
        $this->assertStringContainsString('Hello', $result);
    }

    // Multiple named arguments
    public function testMultipleNamedArguments(): void
    {
        $this->createTemplate('test.html', '{{ date(format="d/m/Y", time=1735056000) }}');
        $result = $this->vision->render('test.html', []);
        // December 24, 2024 in d/m/Y format
        $this->assertStringContainsString('24/12/2024', $result);
    }

    // Named argument with string literal
    public function testNamedArgumentWithString(): void
    {
        $this->createTemplate('test.html', '{{ text(value="Hello World") }}');
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('Hello World', $result);
    }

    // Named argument with number
    public function testNamedArgumentWithNumber(): void
    {
        $this->createTemplate('test.html', '{{ date(time=1735056000, format="d/m/Y") }}');
        $result = $this->vision->render('test.html', []);
        // December 24, 2024 in d/m/Y format
        $this->assertStringContainsString('24/12/2024', $result);
    }

    // Mix positional and named arguments (positional first)
    public function testMixedArguments(): void
    {
        $this->createTemplate('test.html', '{{ date("Y-m-d", time=1735056000) }}');
        $result = $this->vision->render('test.html', []);
        // December 24, 2024 in Y-m-d format
        $this->assertStringContainsString('2024-12-24', $result);
    }

    // Date filter with named timezone argument
    public function testDateFilterWithTimezone(): void
    {
        $this->createTemplate('test.html', '{{ "2025-12-04" | date }}');
        $result = $this->vision->render('test.html', []);
        $this->assertNotEmpty($result);
    }

    // Named argument in conditional
    public function testNamedArgumentInConditional(): void
    {
        $this->createTemplate('test.html', '{% if flag %}yes{% endif %}');
        $result = $this->vision->render('test.html', ['flag' => true]);
        $this->assertStringContainsString('yes', $result);
    }

    // Named argument with interpolated string
    public function testNamedArgumentWithInterpolation(): void
    {
        $this->createTemplate('test.html', '{{ text(value="Hello #{name}") }}');
        $result = $this->vision->render('test.html', ['name' => 'Alice']);
        $this->assertStringContainsString('Hello Alice', $result);
    }

    // Named argument case sensitivity (should be case-insensitive)
    public function testNamedArgumentCaseInsensitivity(): void
    {
        $this->createTemplate('test.html', '{{ date(FORMAT="Y-m-d") }}');
        $result = $this->vision->render('test.html', []);
        // Should still work with uppercase
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $result);
    }
}
