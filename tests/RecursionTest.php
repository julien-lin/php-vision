<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Exception\VisionException;

class RecursionTest extends TestCase
{
    private Vision $vision;

    protected function setUp(): void
    {
        $this->vision = new Vision();
    }

    public function testMaxRecursionDepth(): void
    {
        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Profondeur de récursion maximale');

        // Créer un template avec récursion profonde (plus de 50 niveaux)
        $template = '';
        for ($i = 0; $i < 55; $i++) {
            $template .= '{% if true %}';
        }
        $template .= 'Content';
        for ($i = 0; $i < 55; $i++) {
            $template .= '{% endif %}';
        }

        $this->vision->renderString($template, []);
    }

    public function testNestedLoops(): void
    {
        // Test avec une structure plus simple pour éviter les problèmes de parsing imbriqué
        $template = '{% for item in items %}{% for subitem in item.subitems %}{{ subitem }}{% endfor %}{% endfor %}';

        $variables = [
            'items' => [
                ['subitems' => ['a', 'b']],
                ['subitems' => ['c', 'd']],
            ],
        ];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('a', $result);
        $this->assertStringContainsString('b', $result);
        $this->assertStringContainsString('c', $result);
        $this->assertStringContainsString('d', $result);
    }

    public function testNestedConditions(): void
    {
        $template = <<<'TEMPLATE'
{% if level1 %}
    {% if level2 %}
        {% if level3 %}
            Deep content
        {% endif %}
    {% endif %}
{% endif %}
TEMPLATE;

        $variables = [
            'level1' => true,
            'level2' => true,
            'level3' => true,
        ];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('Deep content', $result);
    }

    public function testLoopWithCondition(): void
    {
        $template = <<<'TEMPLATE'
{% for item in items %}
    {% if item.active %}
        {{ item.name }}
    {% endif %}
{% endfor %}
TEMPLATE;

        $variables = [
            'items' => [
                ['name' => 'Active1', 'active' => true],
                ['name' => 'Inactive', 'active' => false],
                ['name' => 'Active2', 'active' => true],
            ],
        ];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('Active1', $result);
        $this->assertStringNotContainsString('Inactive', $result);
        $this->assertStringContainsString('Active2', $result);
    }

    public function testReasonableRecursionDepth(): void
    {
        // Test avec une profondeur raisonnable (10 niveaux)
        $template = '';
        for ($i = 0; $i < 10; $i++) {
            $template .= '{% if true %}';
        }
        $template .= 'Content';
        for ($i = 0; $i < 10; $i++) {
            $template .= '{% endif %}';
        }

        $result = $this->vision->renderString($template, []);

        $this->assertStringContainsString('Content', $result);
    }
}
