<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class PerformanceTest extends TestCase
{
    private Vision $vision;

    protected function setUp(): void
    {
        $this->vision = new Vision();
    }

    public function testLargeTemplatePerformance(): void
    {
        // Créer un template avec beaucoup de variables
        $template = '';
        $variables = [];
        
        for ($i = 0; $i < 100; $i++) {
            $template .= '{{ var' . $i . ' }} ';
            $variables['var' . $i] = 'value' . $i;
        }

        $start = microtime(true);
        $result = $this->vision->renderString($template, $variables);
        $duration = microtime(true) - $start;

        // Devrait prendre moins de 100ms
        $this->assertLessThan(0.1, $duration, 'Template rendering took too long');
        $this->assertStringContainsString('value0', $result);
        $this->assertStringContainsString('value99', $result);
    }

    public function testNestedLoopsPerformance(): void
    {
        $template = '{% for i in items %}{% for j in i.subitems %}{{ j }}{% endfor %}{% endfor %}';
        
        $variables = [
            'items' => []
        ];
        
        // Créer 10 items avec 10 subitems chacun
        for ($i = 0; $i < 10; $i++) {
            $variables['items'][] = [
                'subitems' => range(1, 10)
            ];
        }

        $start = microtime(true);
        $result = $this->vision->renderString($template, $variables);
        $duration = microtime(true) - $start;

        // Devrait prendre moins de 50ms
        $this->assertLessThan(0.05, $duration, 'Nested loops took too long');
        $this->assertStringContainsString('1', $result);
    }

    public function testComplexConditionsPerformance(): void
    {
        $template = '';
        for ($i = 0; $i < 50; $i++) {
            $template .= '{% if var' . $i . ' >= 10 %}yes{% else %}no{% endif %}';
        }

        $variables = [];
        for ($i = 0; $i < 50; $i++) {
            $variables['var' . $i] = $i;
        }

        $start = microtime(true);
        $result = $this->vision->renderString($template, $variables);
        $duration = microtime(true) - $start;

        // Devrait prendre moins de 100ms
        $this->assertLessThan(0.1, $duration, 'Complex conditions took too long');
    }

    public function testIteratorConversionPerformance(): void
    {
        // Test que les iterators sont convertis correctement sans perte de performance
        $generator = function() {
            for ($i = 0; $i < 100; $i++) {
                yield $i;
            }
        };

        $template = '{% for item in items %}{{ item }}{% endfor %}';
        $variables = ['items' => $generator()];

        $start = microtime(true);
        $result = $this->vision->renderString($template, $variables);
        $duration = microtime(true) - $start;

        // Devrait prendre moins de 50ms
        $this->assertLessThan(0.05, $duration);
        $this->assertStringContainsString('0', $result);
        $this->assertStringContainsString('99', $result);
    }

    public function testFilterChainPerformance(): void
    {
        $template = '{{ text|upper|trim|lower }}';
        $variables = ['text' => '  Hello World  '];

        $iterations = 1000;
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->vision->renderString($template, $variables);
        }
        
        $duration = microtime(true) - $start;
        $avgDuration = $duration / $iterations;

        // Chaque itération devrait prendre moins de 1ms
        $this->assertLessThan(0.001, $avgDuration, 'Filter chain too slow');
    }
}
