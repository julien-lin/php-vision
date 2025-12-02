<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class LoopTest extends TestCase
{
    private Vision $vision;

    protected function setUp(): void
    {
        $this->vision = new Vision();
    }

    public function testSimpleLoop(): void
    {
        $template = '{% for item in items %}{{ item }}{% endfor %}';
        $variables = ['items' => ['a', 'b', 'c']];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('abc', $result);
    }

    public function testLoopWithIndex(): void
    {
        $template = '{% for item in items %}{{ loop.index }}:{{ item }}{% endfor %}';
        $variables = ['items' => ['a', 'b']];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('1:a', $result);
        $this->assertStringContainsString('2:b', $result);
    }

    public function testLoopWithIndex0(): void
    {
        $template = '{% for item in items %}{{ loop.index0 }}{% endfor %}';
        $variables = ['items' => ['a', 'b', 'c']];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('0', $result);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('2', $result);
    }

    public function testLoopFirst(): void
    {
        $template = '{% for item in items %}{% if loop.first %}First:{% endif %}{{ item }}{% endfor %}';
        $variables = ['items' => ['a', 'b', 'c']];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('First:a', $result);
        $this->assertStringNotContainsString('First:b', $result);
    }

    public function testLoopLast(): void
    {
        $template = '{% for item in items %}{{ item }}{% if loop.last %} (Last){% endif %}{% endfor %}';
        $variables = ['items' => ['a', 'b', 'c']];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('c (Last)', $result);
        $this->assertStringNotContainsString('a (Last)', $result);
    }

    public function testLoopLength(): void
    {
        $template = 'Total: {{ items|length }}, Loop: {% for item in items %}{{ loop.length }}{% endfor %}';
        $variables = ['items' => ['a', 'b', 'c']];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('Total: 3', $result);
        $this->assertStringContainsString('Loop: 333', $result); // 3 fois car 3 itérations
    }

    public function testLoopWithEmptyArray(): void
    {
        $template = '{% for item in items %}{{ item }}{% endfor %}';
        $variables = ['items' => []];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('', $result);
    }

    public function testLoopWithAssociativeArray(): void
    {
        $template = '{% for item in items %}{{ item.key }}:{{ item.value }}{% endfor %}';
        $variables = [
            'items' => [
                ['key' => 'name', 'value' => 'John'],
                ['key' => 'age', 'value' => '30'],
            ],
        ];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('name:John', $result);
        $this->assertStringContainsString('age:30', $result);
    }

    public function testLoopWithTraversable(): void
    {
        $template = '{% for item in items %}{{ item }}{% endfor %}';
        $variables = ['items' => new \ArrayIterator(['a', 'b', 'c'])];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('abc', $result);
    }

    public function testLoopWithNonIterable(): void
    {
        $template = '{% for item in items %}{{ item }}{% endfor %}';
        $variables = ['items' => 'not an array'];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('', $result);
    }
}
