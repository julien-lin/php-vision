<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Filters\AbstractFilter;
use JulienLinard\Vision\Filters\FilterManager;

class FilterTest extends TestCase
{
    private Vision $vision;

    protected function setUp(): void
    {
        $this->vision = new Vision();
    }

    public function testAllBuiltInFilters(): void
    {
        $tests = [
            ['template' => '{{ name|upper }}', 'vars' => ['name' => 'hello'], 'expected' => 'HELLO'],
            ['template' => '{{ name|lower }}', 'vars' => ['name' => 'WORLD'], 'expected' => 'world'],
            ['template' => '{{ name|trim }}', 'vars' => ['name' => '  test  '], 'expected' => 'test'],
            ['template' => '{{ name|default:"Default" }}', 'vars' => ['name' => ''], 'expected' => 'Default'],
            ['template' => '{{ count|length }}', 'vars' => ['count' => 'hello'], 'expected' => '5'],
            ['template' => '{{ items|length }}', 'vars' => ['items' => [1, 2, 3]], 'expected' => '3'],
        ];

        foreach ($tests as $test) {
            $result = $this->vision->renderString($test['template'], $test['vars']);
            $this->assertEquals($test['expected'], $result, "Failed for template: {$test['template']}");
        }
    }

    public function testDateFormatFilter(): void
    {
        $tests = [
            ['date' => '2025-01-15 10:30:00', 'format' => 'Y-m-d', 'expected' => '2025-01-15'],
            ['date' => '2025-01-15', 'format' => 'd/m/Y', 'expected' => '15/01/2025'],
            ['date' => time(), 'format' => 'Y', 'expected' => (string)date('Y')],
        ];

        foreach ($tests as $test) {
            $template = '{{ date|date:"' . $test['format'] . '" }}';
            $result = $this->vision->renderString($template, ['date' => $test['date']]);
            $this->assertEquals($test['expected'], $result);
        }
    }

    public function testNumberFormatFilter(): void
    {
        $tests = [
            ['value' => 1234.567, 'decimals' => 2, 'expected' => '1,234.57'],
            ['value' => 1234.5, 'decimals' => 0, 'expected' => '1,235'],
            ['value' => 1000, 'decimals' => 2, 'expected' => '1,000.00'],
        ];

        foreach ($tests as $test) {
            $template = '{{ value|number:' . $test['decimals'] . ' }}';
            $result = $this->vision->renderString($template, ['value' => $test['value']]);
            $this->assertEquals($test['expected'], $result);
        }
    }

    public function testJsonFilter(): void
    {
        $data = ['name' => 'Test', 'age' => 30];
        $template = '{{ data|json }}';
        $result = $this->vision->renderString($template, ['data' => $data]);

        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertEquals($data, $decoded);
    }

    public function testMultipleFiltersChained(): void
    {
        $template = '{{ name|trim|upper|lower }}';
        $result = $this->vision->renderString($template, ['name' => '  HELLO  ']);

        $this->assertEquals('hello', $result);
    }

    public function testFilterWithParameters(): void
    {
        $template = '{{ date|date:"d/m/Y H:i" }}';
        $result = $this->vision->renderString($template, ['date' => '2025-01-15 14:30:00']);

        $this->assertEquals('15/01/2025 14:30', $result);
    }

    public function testCustomFilter(): void
    {
        $this->vision->registerFilter(new class extends AbstractFilter {
            public function getName(): string
            {
                return 'capitalize';
            }

            public function apply(mixed $value, array $params = []): mixed
            {
                if (!is_string($value)) {
                    return $value;
                }
                return ucfirst(strtolower($value));
            }
        });

        $template = '{{ name|capitalize }}';
        $result = $this->vision->renderString($template, ['name' => 'HELLO']);

        $this->assertEquals('Hello', $result);
    }

    public function testFilterWithMultipleParameters(): void
    {
        $this->vision->registerFilter(new class extends AbstractFilter {
            public function getName(): string
            {
                return 'substring';
            }

            public function apply(mixed $value, array $params = []): mixed
            {
                if (!is_string($value)) {
                    return $value;
                }
                $start = isset($params[0]) ? (int)$params[0] : 0;
                $length = isset($params[1]) ? (int)$params[1] : null;
                return $length !== null ? substr($value, $start, $length) : substr($value, $start);
            }
        });

        $template = '{{ text|substring:0,5 }}';
        $result = $this->vision->renderString($template, ['text' => 'Hello World']);

        $this->assertEquals('Hello', $result);
    }

    /**
     * Test que le cache des paramètres de filtres fonctionne correctement
     * Ceci vérifie l'optimisation de performance qui évite le re-parsing répétitif
     */
    public function testFilterParamsCache(): void
    {
        $filterManager = new FilterManager();
        
        // Enregistrer un filtre de test avec paramètres
        $filterManager->addFilter(new class extends AbstractFilter {
            public function getName(): string
            {
                return 'test';
            }

            public function apply(mixed $value, array $params = []): mixed
            {
                return $value . ':' . implode(',', $params);
            }
        });

        // Premier appel avec paramètres - doit parser et mettre en cache
        $result1 = $filterManager->apply('test:param1,param2', 'value1');
        $this->assertEquals('value1:param1,param2', $result1);

        // Deuxième appel avec les mêmes paramètres - doit utiliser le cache
        $result2 = $filterManager->apply('test:param1,param2', 'value2');
        $this->assertEquals('value2:param1,param2', $result2);

        // Vérifier que le résultat est cohérent (cache fonctionne)
        $this->assertEquals($result1, str_replace('value2', 'value1', $result2));

        // Test avec paramètres différents
        $result3 = $filterManager->apply('test:param3,param4', 'value3');
        $this->assertEquals('value3:param3,param4', $result3);

        // Test nettoyage du cache
        $filterManager->clearParamsCache();
        
        // Après nettoyage, le cache devrait être vide mais le parsing devrait toujours fonctionner
        $result4 = $filterManager->apply('test:param1,param2', 'value4');
        $this->assertEquals('value4:param1,param2', $result4);
    }
}
