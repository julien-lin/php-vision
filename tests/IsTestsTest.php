<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class IsTestsTest extends TestCase
{
    private Vision $vision;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_is_' . uniqid();
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

    // Tests for "is defined"
    public function testIsDefinedWithVariable(): void
    {
        $this->createTemplate('test.html', "{{ name is defined ? 'exists' : 'not exists' }}");
        $result = $this->vision->render('test.html', ['name' => 'John']);
        $this->assertStringContainsString('exists', $result);
    }

    public function testIsDefinedWithoutVariable(): void
    {
        $this->createTemplate('test.html', "{{ name is defined ? 'exists' : 'not exists' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('not exists', $result);
    }

    public function testIsDefinedWithNull(): void
    {
        $this->createTemplate('test.html', "{{ name is defined ? 'exists' : 'not exists' }}");
        $result = $this->vision->render('test.html', ['name' => null]);
        $this->assertStringContainsString('exists', $result);
    }

    public function testIsDefinedWithNestedProperty(): void
    {
        $this->createTemplate('test.html', "{{ user.email is defined ? 'has email' : 'no email' }}");
        $result = $this->vision->render('test.html', ['user' => ['email' => 'test@example.com']]);
        $this->assertStringContainsString('has email', $result);
    }

    // Tests for "is null"
    public function testIsNullWithNull(): void
    {
        $this->createTemplate('test.html', "{{ value is null ? 'null' : 'not null' }}");
        $result = $this->vision->render('test.html', ['value' => null]);
        $this->assertStringContainsString('null', $result);
    }

    public function testIsNullWithValue(): void
    {
        $this->createTemplate('test.html', "{{ value is null ? 'null' : 'not null' }}");
        $result = $this->vision->render('test.html', ['value' => 'hello']);
        $this->assertStringContainsString('not null', $result);
    }

    public function testIsNullWithZero(): void
    {
        $this->createTemplate('test.html', "{{ value is null ? 'null' : 'not null' }}");
        $result = $this->vision->render('test.html', ['value' => 0]);
        $this->assertStringContainsString('not null', $result);
    }

    public function testIsNullWithEmptyString(): void
    {
        $this->createTemplate('test.html', "{{ value is null ? 'null' : 'not null' }}");
        $result = $this->vision->render('test.html', ['value' => '']);
        $this->assertStringContainsString('not null', $result);
    }

    public function testIsNullWithFalse(): void
    {
        $this->createTemplate('test.html', "{{ value is null ? 'null' : 'not null' }}");
        $result = $this->vision->render('test.html', ['value' => false]);
        $this->assertStringContainsString('not null', $result);
    }

    // Tests for "is empty"
    public function testIsEmptyWithEmpty(): void
    {
        $this->createTemplate('test.html', "{{ value is empty ? 'empty' : 'not empty' }}");
        $result = $this->vision->render('test.html', ['value' => '']);
        $this->assertStringContainsString('empty', $result);
    }

    public function testIsEmptyWithValue(): void
    {
        $this->createTemplate('test.html', "{{ value is empty ? 'empty' : 'not empty' }}");
        $result = $this->vision->render('test.html', ['value' => 'hello']);
        $this->assertStringContainsString('not empty', $result);
    }

    public function testIsEmptyWithZero(): void
    {
        $this->createTemplate('test.html', "{{ value is empty ? 'empty' : 'not empty' }}");
        $result = $this->vision->render('test.html', ['value' => 0]);
        $this->assertStringContainsString('empty', $result);
    }

    public function testIsEmptyWithFalse(): void
    {
        $this->createTemplate('test.html', "{{ value is empty ? 'empty' : 'not empty' }}");
        $result = $this->vision->render('test.html', ['value' => false]);
        $this->assertStringContainsString('empty', $result);
    }

    public function testIsEmptyWithNull(): void
    {
        $this->createTemplate('test.html', "{{ value is empty ? 'empty' : 'not empty' }}");
        $result = $this->vision->render('test.html', ['value' => null]);
        $this->assertStringContainsString('empty', $result);
    }

    public function testIsEmptyWithEmptyArray(): void
    {
        $this->createTemplate('test.html', "{{ items is empty ? 'empty' : 'not empty' }}");
        $result = $this->vision->render('test.html', ['items' => []]);
        $this->assertStringContainsString('empty', $result);
    }

    public function testIsEmptyWithArrayWithItems(): void
    {
        $this->createTemplate('test.html', "{{ items is empty ? 'empty' : 'not empty' }}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3]]);
        $this->assertStringContainsString('not empty', $result);
    }

    // Tests combining "is" with other operators
    public function testIsDefinedWithComparison(): void
    {
        $this->createTemplate('test.html', "{{ name is defined && name == 'John' ? 'John exists' : 'no John' }}");
        $result = $this->vision->render('test.html', ['name' => 'John']);
        $this->assertStringContainsString('John exists', $result);
    }

    public function testIsNullInCondition(): void
    {
        $this->createTemplate('test.html', "{% if value is null %}null{% else %}not null{% endif %}");
        $result = $this->vision->render('test.html', ['value' => null]);
        $this->assertStringContainsString('null', $result);
    }

    public function testIsEmptyInLoop(): void
    {
        $this->createTemplate('test.html', "{% if items is empty %}no items{% else %}{{ items | length }} items{% endif %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3]]);
        $this->assertStringContainsString('3 items', $result);
    }

    // Test "is not" negation
    public function testIsNotDefined(): void
    {
        $this->createTemplate('test.html', "{{ name is not defined ? 'undefined' : 'defined' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('undefined', $result);
    }

    public function testIsNotNull(): void
    {
        $this->createTemplate('test.html', "{{ value is not null ? 'not null' : 'null' }}");
        $result = $this->vision->render('test.html', ['value' => 'hello']);
        $this->assertStringContainsString('not null', $result);
    }

    public function testIsNotEmpty(): void
    {
        $this->createTemplate('test.html', "{{ value is not empty ? 'not empty' : 'empty' }}");
        $result = $this->vision->render('test.html', ['value' => 'hello']);
        $this->assertStringContainsString('not empty', $result);
    }
}
