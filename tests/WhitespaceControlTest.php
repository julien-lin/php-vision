<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class WhitespaceControlTest extends TestCase
{
    private Vision $vision;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_whitespace_' . uniqid();
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

    // Strip left whitespace
    public function testStripLeftWhitespace(): void
    {
        $this->createTemplate('test.html', "  \n  {%- if true %}yes{% endif %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
        // Should not have leading whitespace
        $this->assertFalse(strpos($result, "  \n") === 0);
    }

    // Strip right whitespace
    public function testStripRightWhitespace(): void
    {
        $this->createTemplate('test.html', "{% if true %}yes{% endif -%}  \n  ");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
        // Should not have trailing whitespace
        $this->assertFalse(strpos(trim($result), "  \n") !== false);
    }

    // Strip both sides (left and right)
    public function testStripBothSides(): void
    {
        $this->createTemplate('test.html', "  \n  {%- if true %}yes{% endif -%}  \n  ");
        $result = $this->vision->render('test.html', []);
        $this->assertEquals('yes', trim($result));
    }

    // Strip whitespace in variable output
    public function testStripVariableOutput(): void
    {
        $this->createTemplate('test.html', "text: {{- 'value' -}} :end");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('text:value:end', $result);
    }

    // Strip whitespace in for loop
    public function testStripForLoop(): void
    {
        $this->createTemplate('test.html', "{% for i in 1..3 -%}\n{{ i }}\n{%- endfor %}");
        $result = $this->vision->render('test.html', []);
        // Should remove newlines around loop content
        $this->assertStringContainsString('123', str_replace("\n", "", $result));
    }

    // No strip when not using minus signs
    public function testNoStripWithoutMinusSigns(): void
    {
        $this->createTemplate('test.html', "  \n  {% if true %}yes{% endif %}  \n  ");
        $result = $this->vision->render('test.html', []);
        // Should preserve whitespace
        $this->assertStringContainsString("  \n", $result);
    }

    // Strip left and keep right
    public function testStripLeftKeepRight(): void
    {
        $this->createTemplate('test.html', "  {% if true -%}yes {%- endif %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
    }

    // Complex nested whitespace control
    public function testComplexNestedWhitespace(): void
    {
        // {%- and -%} control whitespace; {{- and -}} do the same for variables
        // The template should strip newlines and internal whitespace  
        $this->createTemplate('test.html', "{% for i in 1..2 -%}\n  Item {{ i }}\n{%- endfor %}");
        $result = $this->vision->render('test.html', []);
        // Should strip newlines and leading spaces - result is Item 1Item 2
        $this->assertStringContainsString('Item 1', $result);
        $this->assertStringContainsString('Item 2', $result);
    }

    // Whitespace in conditional with else
    public function testWhitespaceInConditional(): void
    {
        $this->createTemplate('test.html', "{% if false %}no{% else -%}\nyes{%- endif %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    // Whitespace control with filter
    public function testWhitespaceControlWithFilter(): void
    {
        $this->createTemplate('test.html', "{{- 'hello' | upper -}}");
        $result = $this->vision->render('test.html', []);
        $this->assertEquals('HELLO', trim($result));
    }
}
