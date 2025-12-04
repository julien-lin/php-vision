<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class ForElseTest extends TestCase
{
    private Vision $vision;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_forelse_' . uniqid();
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

    // Basic for...else
    public function testForElseWithItems(): void
    {
        $this->createTemplate('test.html', "{% for item in items %}{{ item }}{% else %}No items{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3]]);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('3', $result);
        $this->assertStringNotContainsString('No items', $result);
    }

    public function testForElseWithoutItems(): void
    {
        $this->createTemplate('test.html', "{% for item in items %}{{ item }}{% else %}No items{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => []]);
        $this->assertStringContainsString('No items', $result);
        $this->assertStringNotContainsString('1', $result);
    }

    public function testForElseWithUndefinedVariable(): void
    {
        $this->createTemplate('test.html', "{% for item in items %}{{ item }}{% else %}No items{% endfor %}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('No items', $result);
    }

    // for...else with conditions
    public function testForElseWithCondition(): void
    {
        $this->createTemplate('test.html', "{% for item in items if item > 2 %}{{ item }},{% else %}No matching items{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4, 5]]);
        $this->assertStringContainsString('3', $result);
        $this->assertStringContainsString('4', $result);
        $this->assertStringContainsString('5', $result);
        $this->assertStringNotContainsString('No matching items', $result);
    }

    public function testForElseWithConditionNoMatch(): void
    {
        $this->createTemplate('test.html', "{% for item in items if item > 10 %}{{ item }}{% else %}No matching items{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3]]);
        $this->assertStringContainsString('No matching items', $result);
    }

    // Nested for...else
    public function testNestedForElse(): void
    {
        $template = <<<'TPL'
{% for group in groups %}
  {{ group.name }}:
  {% for item in group.items %}
    - {{ item }}
  {% else %}
    (empty)
  {% endfor %}
{% else %}
  No groups
{% endfor %}
TPL;
        $this->createTemplate('test.html', $template);
        $data = [
            'groups' => [
                ['name' => 'Group 1', 'items' => ['a', 'b']],
                ['name' => 'Group 2', 'items' => []],
                ['name' => 'Group 3', 'items' => ['c']],
            ]
        ];
        $result = $this->vision->render('test.html', $data);
        $this->assertStringContainsString('Group 1', $result);
        $this->assertStringContainsString('a', $result);
        $this->assertStringContainsString('(empty)', $result);
        $this->assertStringContainsString('Group 3', $result);
        $this->assertStringContainsString('c', $result);
    }

    // for...else with foreach
    public function testForElseWithAssociativeArray(): void
    {
        $this->createTemplate('test.html', "{% for key, value in items %}{{ key }}: {{ value }},{% else %}No items{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => ['a' => 1, 'b' => 2]]);
        $this->assertStringContainsString('a: 1', $result);
        $this->assertStringContainsString('b: 2', $result);
        $this->assertStringNotContainsString('No items', $result);
    }

    // Complex scenario
    public function testForElseInIfBlock(): void
    {
        $template = <<<'TPL'
{% if showItems %}
  {% for item in items %}
    Item: {{ item }}
  {% else %}
    No items to display
  {% endfor %}
{% else %}
  Not showing items
{% endif %}
TPL;
        $this->createTemplate('test.html', $template);

        // Test 1: show items with data
        $result = $this->vision->render('test.html', ['showItems' => true, 'items' => [1, 2]]);
        $this->assertStringContainsString('Item: 1', $result);
        $this->assertStringContainsString('Item: 2', $result);
        $this->assertStringNotContainsString('No items to display', $result);

        // Test 2: show items without data
        $result = $this->vision->render('test.html', ['showItems' => true, 'items' => []]);
        $this->assertStringContainsString('No items to display', $result);

        // Test 3: don't show items
        $result = $this->vision->render('test.html', ['showItems' => false, 'items' => [1, 2]]);
        $this->assertStringContainsString('Not showing items', $result);
        $this->assertStringNotContainsString('Item:', $result);
    }

    // loop variable with else
    public function testForElseWithLoopVariable(): void
    {
        $this->createTemplate('test.html', "{% for item in items %}{{ loop.index }}: {{ item }},{% else %}No items{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => ['a', 'b', 'c']]);
        $this->assertStringContainsString('1: a', $result);
        $this->assertStringContainsString('2: b', $result);
        $this->assertStringContainsString('3: c', $result);
        $this->assertStringNotContainsString('No items', $result);
    }
}
