<?php

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Parser\TokenType;
use JulienLinard\Vision\Parser\NodeType;
use JulienLinard\Vision\Parser\ASTNodePool;
use JulienLinard\Vision\Exception\VisionException;

class ParserTest extends TestCase
{
    private TemplateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
    }

    public function testParseSimpleText(): void
    {
        $content = 'Hello World';
        $parsed = $this->parser->parse($content);

        $this->assertCount(1, $parsed->tokens);
        $this->assertEquals(TokenType::TEXT, $parsed->tokens[0]->type);
        $this->assertEquals('Hello World', $parsed->tokens[0]->value);
    }

    public function testParseVariable(): void
    {
        $content = 'Hello {{ name }}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(2, $parsed->tokens);
        $this->assertEquals(TokenType::TEXT, $parsed->tokens[0]->type);
        $this->assertEquals(TokenType::VARIABLE, $parsed->tokens[1]->type);
        $this->assertEquals('{{ name }}', $parsed->tokens[1]->value);
    }

    public function testParseVariableWithFilter(): void
    {
        $content = '{{ name | upper }}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(1, $parsed->tokens);
        $this->assertEquals(TokenType::VARIABLE, $parsed->tokens[0]->type);
        $this->assertArrayHasKey(2, $parsed->tokens[0]->matches);
    }

    public function testParseForLoop(): void
    {
        $content = '{% for item in items %}{{ item }}{% endfor %}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(3, $parsed->tokens);
        $this->assertEquals(TokenType::FOR_START, $parsed->tokens[0]->type);
        $this->assertEquals(TokenType::VARIABLE, $parsed->tokens[1]->type);
        $this->assertEquals(TokenType::FOR_END, $parsed->tokens[2]->type);
    }

    public function testParseIfCondition(): void
    {
        $content = '{% if condition %}Yes{% endif %}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(3, $parsed->tokens);
        $this->assertEquals(TokenType::IF_START, $parsed->tokens[0]->type);
        $this->assertEquals(TokenType::TEXT, $parsed->tokens[1]->type);
        $this->assertEquals(TokenType::IF_END, $parsed->tokens[2]->type);
    }

    public function testParseIfElseCondition(): void
    {
        $content = '{% if condition %}Yes{% else %}No{% endif %}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(5, $parsed->tokens);
        $this->assertEquals(TokenType::IF_START, $parsed->tokens[0]->type);
        $this->assertEquals(TokenType::TEXT, $parsed->tokens[1]->type);
        $this->assertEquals(TokenType::ELSE, $parsed->tokens[2]->type);
        $this->assertEquals(TokenType::TEXT, $parsed->tokens[3]->type);
        $this->assertEquals(TokenType::IF_END, $parsed->tokens[4]->type);
    }

    public function testParseIfElseifCondition(): void
    {
        $content = '{% if x > 5 %}High{% elseif x > 0 %}Low{% else %}Zero{% endif %}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(7, $parsed->tokens);
        $this->assertEquals(TokenType::IF_START, $parsed->tokens[0]->type);
        $this->assertEquals(TokenType::ELSEIF, $parsed->tokens[2]->type);
        $this->assertEquals(TokenType::ELSE, $parsed->tokens[4]->type);
    }

    public function testRemoveComments(): void
    {
        $content = 'Hello {# This is a comment #} World';
        $parsed = $this->parser->parse($content);

        // Le commentaire devrait être supprimé
        $this->assertStringNotContainsString('comment', $parsed->content);
        $this->assertStringContainsString('Hello', $parsed->content);
        $this->assertStringContainsString('World', $parsed->content);
    }

    public function testBuildASTSimpleText(): void
    {
        $content = 'Hello World';
        $parsed = $this->parser->parse($content);

        $this->assertEquals(NodeType::ROOT, $parsed->ast->type);
        $this->assertCount(1, $parsed->ast->children);
        $this->assertEquals(NodeType::TEXT, $parsed->ast->children[0]->type);
        $this->assertEquals('Hello World', $parsed->ast->children[0]->value);
    }

    public function testBuildASTVariable(): void
    {
        $content = '{{ name }}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(1, $parsed->ast->children);
        $this->assertEquals(NodeType::VARIABLE, $parsed->ast->children[0]->type);
    }

    public function testBuildASTForLoop(): void
    {
        $content = '{% for item in items %}{{ item }}{% endfor %}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(1, $parsed->ast->children);
        $forNode = $parsed->ast->children[0];
        $this->assertEquals(NodeType::FOR_LOOP, $forNode->type);
        $this->assertCount(1, $forNode->children);
        $this->assertEquals(NodeType::VARIABLE, $forNode->children[0]->type);
    }

    public function testBuildASTIfCondition(): void
    {
        $content = '{% if condition %}Yes{% endif %}';
        $parsed = $this->parser->parse($content);

        $this->assertCount(1, $parsed->ast->children);
        $ifNode = $parsed->ast->children[0];
        $this->assertEquals(NodeType::IF_CONDITION, $ifNode->type);
        $this->assertCount(1, $ifNode->children);
        $this->assertEquals(NodeType::TEXT, $ifNode->children[0]->type);
    }

    public function testBuildASTNestedStructures(): void
    {
        $content = '{% for item in items %}{% if item > 5 %}{{ item }}{% endif %}{% endfor %}';
        $parsed = $this->parser->parse($content);

        $forNode = $parsed->ast->children[0];
        $this->assertEquals(NodeType::FOR_LOOP, $forNode->type);
        
        $ifNode = $forNode->children[0];
        $this->assertEquals(NodeType::IF_CONDITION, $ifNode->type);
        
        $varNode = $ifNode->children[0];
        $this->assertEquals(NodeType::VARIABLE, $varNode->type);
    }

    public function testParseComplexTemplate(): void
    {
        $content = <<<'TPL'
        {# Header #}
        <h1>{{ title | upper }}</h1>
        {% for user in users %}
            {% if user.active %}
                <p>{{ user.name }}</p>
            {% else %}
                <p>Inactive</p>
            {% endif %}
        {% endfor %}
        TPL;

        $parsed = $this->parser->parse($content);

        // Vérifier que le parsing s'est bien déroulé
        $this->assertGreaterThan(5, count($parsed->tokens));
        $this->assertEquals(NodeType::ROOT, $parsed->ast->type);
        $this->assertNotEmpty($parsed->ast->children);
    }

    /**
     * Test que ASTNodePool fonctionne correctement
     * 
     * Note: Avec les propriétés readonly de ASTNode, la vraie réutilisation
     * n'est pas possible, mais le pool nettoie les références pour aider le GC.
     */
    public function testASTNodePool(): void
    {
        $pool = new ASTNodePool();
        
        // Acquérir des nœuds
        $node1 = $pool->acquire(NodeType::TEXT, 'Hello');
        $this->assertEquals(NodeType::TEXT, $node1->type);
        $this->assertEquals('Hello', $node1->value);
        
        $node2 = $pool->acquire(NodeType::VARIABLE, 'name');
        $this->assertEquals(NodeType::VARIABLE, $node2->type);
        $this->assertEquals('name', $node2->value);
        
        // Libérer les nœuds (nettoie les références)
        $node1->addChild($node2);
        $this->assertNotEmpty($node1->children);
        
        $pool->release($node1);
        // Après release, les enfants devraient être nettoyés
        $this->assertEmpty($node1->children);
        
        // Test statistiques
        $stats = $pool->getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_nodes', $stats);
        $this->assertArrayHasKey('pool_size', $stats);
        
        // Test nettoyage
        $pool->clear();
        $statsAfter = $pool->getStats();
        $this->assertEquals(0, $statsAfter['total_nodes']);
    }
}
