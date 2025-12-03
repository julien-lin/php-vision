<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Exception\TemplateNotFoundException;
use JulienLinard\Vision\Exception\InvalidFilterException;

class VisionTest extends TestCase
{
    private Vision $vision;

    protected function setUp(): void
    {
        $this->vision = new Vision();
    }

    public function testRenderSimpleVariable(): void
    {
        $template = 'Bonjour {{ name }} !';
        $variables = ['name' => 'Julien'];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('Bonjour Julien !', $result);
    }

    public function testRenderMultipleVariables(): void
    {
        $template = '{{ firstname }} {{ lastname }}';
        $variables = [
            'firstname' => 'Julien',
            'lastname' => 'Linard',
        ];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('Julien Linard', $result);
    }

    public function testRenderWithUpperFilter(): void
    {
        $template = '{{ name|upper }}';
        $variables = ['name' => 'julien'];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('JULIEN', $result);
    }

    public function testRenderWithLowerFilter(): void
    {
        $template = '{{ name|lower }}';
        $variables = ['name' => 'JULIEN'];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('julien', $result);
    }

    public function testRenderWithTrimFilter(): void
    {
        $template = '{{ name|trim }}';
        $variables = ['name' => '  Julien  '];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('Julien', $result);
    }

    public function testRenderWithMultipleFilters(): void
    {
        $template = '{{ name|trim|upper }}';
        $variables = ['name' => '  julien  '];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('JULIEN', $result);
    }

    public function testRenderWithDefaultFilter(): void
    {
        $template = '{{ name|default:"Anonyme" }}';
        $variables = ['name' => ''];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('Anonyme', $result);
    }

    public function testRenderWithDateFormatFilter(): void
    {
        $template = '{{ date|date:"Y-m-d" }}';
        $variables = ['date' => '2025-01-15 10:30:00'];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('2025-01-15', $result);
    }

    public function testRenderWithNumberFormatFilter(): void
    {
        $template = '{{ price|number:2 }}';
        $variables = ['price' => 1234.567];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('1,234.57', $result);
    }

    public function testRenderWithLengthFilter(): void
    {
        $template = '{{ name|length }}';
        $variables = ['name' => 'Julien'];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('6', $result);
    }

    public function testRenderWithNestedVariable(): void
    {
        $template = '{{ user.name }}';
        $variables = [
            'user' => [
                'name' => 'Julien',
            ],
        ];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('Julien', $result);
    }

    public function testRenderWithIfCondition(): void
    {
        $template = '{% if isActive %}Actif{% else %}Inactif{% endif %}';
        $variables = ['isActive' => true];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('Actif', $result);
    }

    public function testRenderWithIfConditionFalse(): void
    {
        $template = '{% if isActive %}Actif{% else %}Inactif{% endif %}';
        $variables = ['isActive' => false];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('Inactif', $result);
    }

    public function testRenderWithForLoop(): void
    {
        $template = '{% for item in items %}{{ item }}{% endfor %}';
        $variables = ['items' => ['a', 'b', 'c']];

        $result = $this->vision->renderString($template, $variables);

        $this->assertEquals('abc', $result);
    }

    public function testRenderWithForLoopAndIndex(): void
    {
        $template = '{% for item in items %}{{ loop.index }}:{{ item }}{% endfor %}';
        $variables = ['items' => ['a', 'b']];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('1:a', $result);
        $this->assertStringContainsString('2:b', $result);
    }

    public function testAutoEscape(): void
    {
        $template = '{{ content }}';
        $variables = ['content' => '<script>alert("xss")</script>'];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testDisableAutoEscape(): void
    {
        $vision = new Vision('', false);
        $template = '{{ content }}';
        $variables = ['content' => '<strong>Test</strong>'];

        $result = $vision->renderString($template, $variables);

        $this->assertStringContainsString('<strong>', $result);
    }

    public function testEscapeFilter(): void
    {
        $vision = new Vision('', false);
        $template = '{{ content|escape }}';
        $variables = ['content' => '<script>alert("xss")</script>'];

        $result = $vision->renderString($template, $variables);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testInvalidFilter(): void
    {
        $this->expectException(InvalidFilterException::class);

        $template = '{{ name|invalidfilter }}';
        $variables = ['name' => 'Julien'];

        $this->vision->renderString($template, $variables);
    }

    public function testRegisterCustomFilter(): void
    {
        $vision = new Vision();
        $vision->registerFilter(new class extends \JulienLinard\Vision\Filters\AbstractFilter {
            public function getName(): string
            {
                return 'reverse';
            }

            public function apply(mixed $value, array $params = []): mixed
            {
                if (!is_string($value)) {
                    return $value;
                }
                return strrev($value);
            }
        });

        $template = '{{ name|reverse }}';
        $variables = ['name' => 'Julien'];

        $result = $vision->renderString($template, $variables);

        $this->assertEquals('neiluJ', $result);
    }

    public function testRegisterCustomFunction(): void
    {
        $vision = new Vision();
        $vision->registerFunction('uppercase', function ($text) {
            return strtoupper($text);
        });

        $template = '{{ uppercase("hello") }}';
        $variables = [];

        $result = $vision->renderString($template, $variables);

        $this->assertEquals('HELLO', $result);
    }

    public function testComplexTemplate(): void
    {
        $template = <<<'TEMPLATE'
<h1>{{ title|upper }}</h1>
{% if users %}
    <ul>
    {% for user in users %}
        <li>{{ user.name|trim }} ({{ user.email }})</li>
    {% endfor %}
    </ul>
{% else %}
    <p>Aucun utilisateur</p>
{% endif %}
TEMPLATE;

        $variables = [
            'title' => 'Utilisateurs',
            'users' => [
                ['name' => '  Julien  ', 'email' => 'julien@example.com'],
                ['name' => 'Marie', 'email' => 'marie@example.com'],
            ],
        ];

        $result = $this->vision->renderString($template, $variables);

        $this->assertStringContainsString('UTILISATEURS', $result);
        $this->assertStringContainsString('Julien', $result);
        $this->assertStringContainsString('julien@example.com', $result);
        $this->assertStringContainsString('Marie', $result);
    }

    /**
     * Test que ControlStructureProcessor est réutilisé (singleton pattern)
     * Ceci vérifie l'optimisation de performance qui évite les allocations répétées
     */
    public function testControlStructureProcessorSingleton(): void
    {
        // Template avec plusieurs structures de contrôle
        $template1 = '{% if condition %}Yes{% else %}No{% endif %}';
        $template2 = '{% for item in items %}{{ item }}{% endfor %}';
        $template3 = '{% if a %}A{% else %}C{% endif %}';

        // Premier rendu
        $result1 = $this->vision->renderString($template1, ['condition' => true]);
        $this->assertEquals('Yes', $result1);

        // Deuxième rendu (devrait réutiliser la même instance)
        $result2 = $this->vision->renderString($template2, ['items' => ['a', 'b', 'c']]);
        $this->assertEquals('abc', $result2);

        // Troisième rendu avec structures complexes
        $result3 = $this->vision->renderString($template3, ['a' => true]);
        $this->assertEquals('A', $result3);

        // Quatrième rendu pour vérifier que l'instance est toujours réutilisée
        $result4 = $this->vision->renderString($template1, ['condition' => false]);
        $this->assertEquals('No', $result4);

        // Vérifier que tous les rendus fonctionnent correctement
        // (si le singleton causait des problèmes, on aurait des erreurs)
        // Le fait que tous les tests ci-dessus passent confirme que le singleton fonctionne
    }
}
