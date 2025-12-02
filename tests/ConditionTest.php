<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class ConditionTest extends TestCase
{
    private Vision $vision;

    protected function setUp(): void
    {
        $this->vision = new Vision();
    }

    public function testSimpleIfCondition(): void
    {
        $template = '{% if condition %}Yes{% else %}No{% endif %}';
        
        $result1 = $this->vision->renderString($template, ['condition' => true]);
        $this->assertEquals('Yes', $result1);

        $result2 = $this->vision->renderString($template, ['condition' => false]);
        $this->assertEquals('No', $result2);
    }

    public function testIfWithoutElse(): void
    {
        $template = '{% if condition %}Yes{% endif %}';
        
        $result1 = $this->vision->renderString($template, ['condition' => true]);
        $this->assertEquals('Yes', $result1);

        $result2 = $this->vision->renderString($template, ['condition' => false]);
        $this->assertEquals('', $result2);
    }

    public function testComparisonOperators(): void
    {
        // Test >=
        $result1 = $this->vision->renderString('{% if age >= 18 %}Adult{% else %}Minor{% endif %}', ['age' => 20]);
        $this->assertEquals('Adult', $result1);
        
        $result2 = $this->vision->renderString('{% if age >= 18 %}Adult{% else %}Minor{% endif %}', ['age' => 15]);
        $this->assertEquals('Minor', $result2);
        
        // Test >
        $result3 = $this->vision->renderString('{% if count > 10 %}Many{% else %}Few{% endif %}', ['count' => 15]);
        $this->assertEquals('Many', $result3);
        
        // Test <
        $result4 = $this->vision->renderString('{% if count < 10 %}Few{% else %}Many{% endif %}', ['count' => 5]);
        $this->assertEquals('Few', $result4);
        
        // Test ==
        $result5 = $this->vision->renderString('{% if status == "active" %}Active{% else %}Inactive{% endif %}', ['status' => 'active']);
        $this->assertEquals('Active', $result5);
        
        // Test !=
        $result6 = $this->vision->renderString('{% if status != "active" %}Inactive{% else %}Active{% endif %}', ['status' => 'pending']);
        $this->assertEquals('Inactive', $result6);
    }

    public function testNegation(): void
    {
        $template = '{% if !empty %}Not Empty{% else %}Empty{% endif %}';
        
        $result1 = $this->vision->renderString($template, ['empty' => false]);
        $this->assertEquals('Not Empty', $result1);

        $result2 = $this->vision->renderString($template, ['empty' => true]);
        $this->assertEquals('Empty', $result2);
    }

    public function testZeroValue(): void
    {
        $template = '{% if count %}Has Count{% else %}No Count{% endif %}';
        
        $result = $this->vision->renderString($template, ['count' => 0]);
        // 0 devrait être considéré comme true dans notre logique
        $this->assertStringContainsString('Has Count', $result);
    }

    public function testEmptyString(): void
    {
        $template = '{% if name %}Has Name{% else %}No Name{% endif %}';
        
        $result = $this->vision->renderString($template, ['name' => '']);
        $this->assertStringContainsString('No Name', $result);
    }

    public function testNestedConditions(): void
    {
        // Test avec une structure plus simple pour éviter les problèmes de parsing imbriqué
        $template = '{% if user %}{% if user.active %}Active User{% else %}Inactive User{% endif %}{% else %}No User{% endif %}';

        $result1 = $this->vision->renderString($template, ['user' => ['active' => true]]);
        $this->assertStringContainsString('Active User', $result1);

        $result2 = $this->vision->renderString($template, ['user' => ['active' => false]]);
        $this->assertStringContainsString('Inactive User', $result2);

        $result3 = $this->vision->renderString($template, []);
        $this->assertStringContainsString('No User', $result3);
    }
}
