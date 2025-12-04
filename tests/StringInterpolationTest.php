<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class StringInterpolationTest extends TestCase
{
    private Vision $vision;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_interpolation_' . uniqid();
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

    // Basic string interpolation
    public function testBasicInterpolation(): void
    {
        $this->createTemplate('test.html', '{{ "Hello #{name}" }}');
        $result = $this->vision->render('test.html', ['name' => 'World']);
        $this->assertStringContainsString('Hello World', $result);
    }

    // Multiple interpolations in one string
    public function testMultipleInterpolations(): void
    {
        $this->createTemplate('test.html', '{{ "#{first} #{last}" }}');
        $result = $this->vision->render('test.html', ['first' => 'John', 'last' => 'Doe']);
        $this->assertStringContainsString('John Doe', $result);
    }

    // Nested property interpolation
    public function testNestedPropertyInterpolation(): void
    {
        $this->createTemplate('test.html', '{{ "User: #{user.name}" }}');
        $result = $this->vision->render('test.html', ['user' => ['name' => 'Alice']]);
        $this->assertStringContainsString('User: Alice', $result);
    }

    // Interpolation with numbers
    public function testNumberInterpolation(): void
    {
        $this->createTemplate('test.html', '{{ "Count: #{count}" }}');
        $result = $this->vision->render('test.html', ['count' => 42]);
        $this->assertStringContainsString('Count: 42', $result);
    }

    // Interpolation in single quotes (should NOT interpolate)
    public function testSingleQuotesNoInterpolation(): void
    {
        $this->createTemplate('test.html', "{{ 'Hello #{name}' }}");
        $result = $this->vision->render('test.html', ['name' => 'World']);
        $this->assertStringContainsString('Hello #{name}', $result);
    }

    // Escaped interpolation
    public function testEscapedInterpolation(): void
    {
        $this->createTemplate('test.html', '{{ "Price: \\#{price}" }}');
        $result = $this->vision->render('test.html', ['price' => 100]);
        $this->assertStringContainsString('Price: #{price}', $result);
    }

    // Interpolation with array index
    public function testArrayIndexInterpolation(): void
    {
        $this->createTemplate('test.html', '{{ "Item: #{items.0}" }}');
        $result = $this->vision->render('test.html', ['items' => ['apple', 'banana']]);
        $this->assertStringContainsString('Item: apple', $result);
    }

    // Empty interpolation (variable not set)
    public function testEmptyVariableInterpolation(): void
    {
        $this->createTemplate('test.html', '{{ "Hello #{unknown}" }}');
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('Hello ', $result);
    }

    // Interpolation with complex expression (in filter context)
    public function testInterpolationInFilter(): void
    {
        $this->createTemplate('test.html', '{{ "Hello #{name}" | upper }}');
        $result = $this->vision->render('test.html', ['name' => 'World']);
        $this->assertStringContainsString('HELLO WORLD', $result);
    }

    // Interpolation in control structures
    public function testInterpolationInLoop(): void
    {
        $this->createTemplate('test.html', '{% for item in items %}{{ "Item: #{item}" }}{% endfor %}');
        $result = $this->vision->render('test.html', ['items' => ['a', 'b', 'c']]);
        $this->assertStringContainsString('Item: a', $result);
        $this->assertStringContainsString('Item: b', $result);
        $this->assertStringContainsString('Item: c', $result);
    }
}
