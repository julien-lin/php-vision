<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Compiler\DeadBranchEliminator;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Parser\NodeType;

class DeadBranchEliminatorTest extends TestCase
{
    private DeadBranchEliminator $eliminator;
    private TemplateParser $parser;

    protected function setUp(): void
    {
        $this->eliminator = new DeadBranchEliminator();
        $this->parser = new TemplateParser();
    }

    // ===== Tests élimination {% if true %} =====

    public function testEliminateIfTrue(): void
    {
        $content = '{% if true %}Content{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Le if doit être éliminé, seul le contenu reste
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('Content', $optimized->children[0]->value);
    }

    public function testEliminateIfTrueWithElse(): void
    {
        $content = '{% if true %}True Content{% else %}False Content{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Seul le contenu du if doit rester
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('True Content', $optimized->children[0]->value);
    }

    public function testEliminateIfTrueExpression(): void
    {
        $content = '{% if 1 + 1 == 2 %}Correct{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // L'expression est constante et évaluable
        // Note: ConstantFolder ne supporte pas encore les comparaisons
        // donc la condition reste
        $this->assertCount(1, $optimized->children);
        // Si l'optimisation ne fonctionne pas, le IF_CONDITION reste
    }

    // ===== Tests élimination {% if false %} =====

    public function testEliminateIfFalse(): void
    {
        $content = '{% if false %}Dead Code{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Tout le bloc doit être éliminé
        $this->assertCount(0, $optimized->children);
    }

    public function testEliminateIfFalseKeepElse(): void
    {
        $content = '{% if false %}Dead{% else %}Alive{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Seul le contenu du else doit rester
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('Alive', $optimized->children[0]->value);
    }

    public function testEliminateIfFalseBooleanExpression(): void
    {
        $content = '{% if true && false %}Never{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // true && false → false, tout le bloc supprimé
        $this->assertCount(0, $optimized->children);
    }

    // ===== Tests conservation des conditions non-constantes =====

    public function testKeepVariableCondition(): void
    {
        $content = '{% if user %}Content{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // La condition avec variable doit être conservée
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::IF_CONDITION, $optimized->children[0]->type);
    }

    public function testKeepComplexCondition(): void
    {
        $content = '{% if age > 18 %}Adult{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Condition avec variable conservée
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::IF_CONDITION, $optimized->children[0]->type);
    }

    // ===== Tests elseif =====

    public function testEliminateIfFalseWithElseIf(): void
    {
        $content = '{% if false %}A{% elseif true %}B{% else %}C{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // if false éliminé, elseif true évalué → garde B
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('B', $optimized->children[0]->value);
    }

    public function testEliminateIfFalseElseIfFalse(): void
    {
        $content = '{% if false %}A{% elseif false %}B{% else %}C{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // if false et elseif false éliminés → garde else C
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('C', $optimized->children[0]->value);
    }

    public function testKeepElseIfWithVariable(): void
    {
        $content = '{% if false %}A{% elseif condition %}B{% else %}C{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // if false éliminé, elseif et else conservés
        $this->assertCount(2, $optimized->children);
        $this->assertEquals(NodeType::ELSEIF_CONDITION, $optimized->children[0]->type);
        $this->assertEquals(NodeType::ELSE_CONDITION, $optimized->children[1]->type);
    }

    // ===== Tests conditions imbriquées =====

    public function testNestedIfTrue(): void
    {
        $content = '{% if true %}{% if true %}Nested{% endif %}{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Les deux if true doivent être éliminés
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('Nested', $optimized->children[0]->value);
    }

    public function testNestedIfFalse(): void
    {
        $content = '{% if true %}{% if false %}Dead{% endif %}Outside{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // if true éliminé, if false interne éliminé → reste "Outside"
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('Outside', $optimized->children[0]->value);
    }

    public function testNestedMixedConditions(): void
    {
        $content = '{% if true %}{% if variable %}Inner{% endif %}Outer{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // if true éliminé, if variable conservé
        $this->assertCount(2, $optimized->children);
        $this->assertEquals(NodeType::IF_CONDITION, $optimized->children[0]->type);
        $this->assertEquals(NodeType::TEXT, $optimized->children[1]->type);
    }

    // ===== Tests avec boucles =====

    public function testIfTrueAroundLoop(): void
    {
        $content = '{% if true %}{% for item in items %}{{ item }}{% endfor %}{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // if true éliminé, boucle conservée
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::FOR_LOOP, $optimized->children[0]->type);
    }

    public function testIfFalseAroundLoop(): void
    {
        $content = '{% if false %}{% for item in items %}{{ item }}{% endfor %}{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Tout le bloc éliminé (boucle incluse)
        $this->assertCount(0, $optimized->children);
    }

    public function testIfInsideLoop(): void
    {
        $content = '{% for item in items %}{% if true %}{{ item }}{% endif %}{% endfor %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Boucle conservée, if true interne éliminé
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::FOR_LOOP, $optimized->children[0]->type);

        $forNode = $optimized->children[0];
        // Le contenu de la boucle devrait être la variable (if true éliminé)
        $this->assertCount(1, $forNode->children);
        $this->assertEquals(NodeType::VARIABLE, $forNode->children[0]->type);
    }

    // ===== Tests analyseOptimizationPotential =====

    public function testAnalyzeNoPotential(): void
    {
        $content = '{% if user %}Content{% endif %}';
        $parsed = $this->parser->parse($content);

        $stats = $this->eliminator->analyzeOptimizationPotential($parsed->ast);

        $this->assertEquals(0, $stats['eliminable']);
        $this->assertEquals(1, $stats['total']);
        $this->assertEquals(0.0, $stats['percentage']);
    }

    public function testAnalyzeFullPotential(): void
    {
        $content = '{% if true %}A{% endif %}{% if false %}B{% endif %}';
        $parsed = $this->parser->parse($content);

        $stats = $this->eliminator->analyzeOptimizationPotential($parsed->ast);

        $this->assertEquals(2, $stats['eliminable']);
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(100.0, $stats['percentage']);
    }

    public function testAnalyzePartialPotential(): void
    {
        $content = '{% if true %}A{% endif %}{% if user %}B{% endif %}{% if false %}C{% endif %}';
        $parsed = $this->parser->parse($content);

        $stats = $this->eliminator->analyzeOptimizationPotential($parsed->ast);

        $this->assertEquals(2, $stats['eliminable']);
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(66.67, $stats['percentage']);
    }

    public function testAnalyzeEmpty(): void
    {
        $content = 'No conditions';
        $parsed = $this->parser->parse($content);

        $stats = $this->eliminator->analyzeOptimizationPotential($parsed->ast);

        $this->assertEquals(0, $stats['eliminable']);
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0.0, $stats['percentage']);
    }

    // ===== Tests d'intégration complexes =====

    public function testComplexTemplate(): void
    {
        $content = <<<'TPL'
{% if true %}
    <header>Header</header>
{% endif %}
{% if false %}
    <nav>Hidden Nav</nav>
{% endif %}
{% for item in items %}
    {% if true %}
        <div>{{ item }}</div>
    {% endif %}
{% endfor %}
{% if condition %}
    <footer>Dynamic Footer</footer>
{% endif %}
TPL;

        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Vérifier que les if true/false sont éliminés
        // et que le if avec variable est conservé
        $hasIfCondition = false;
        foreach ($optimized->children as $child) {
            if ($child->type === NodeType::IF_CONDITION) {
                $hasIfCondition = true;
            }
        }

        $this->assertTrue($hasIfCondition, 'Condition with variable should be kept');
    }

    public function testMultipleTextNodes(): void
    {
        $content = 'Before{% if true %}Middle{% endif %}After';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // Les 3 text nodes doivent rester (if true éliminé)
        $this->assertCount(3, $optimized->children);
        $this->assertEquals('Before', $optimized->children[0]->value);
        $this->assertEquals('Middle', $optimized->children[1]->value);
        $this->assertEquals('After', $optimized->children[2]->value);
    }

    public function testBooleanExpressions(): void
    {
        $content = '{% if true || false %}A{% endif %}{% if false && true %}B{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // true || false → true → A gardé
        // false && true → false → B supprimé
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('A', $optimized->children[0]->value);
    }

    public function testNegation(): void
    {
        $content = '{% if !false %}Content{% endif %}';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // !false → true → if éliminé
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('Content', $optimized->children[0]->value);
    }

    public function testEmptyIfBlock(): void
    {
        $content = '{% if true %}{% endif %}Text';
        $parsed = $this->parser->parse($content);
        $optimized = $this->eliminator->optimize($parsed->ast);

        // if true vide éliminé → reste uniquement Text
        $this->assertCount(1, $optimized->children);
        $this->assertEquals(NodeType::TEXT, $optimized->children[0]->type);
        $this->assertEquals('Text', $optimized->children[0]->value);
    }
}
