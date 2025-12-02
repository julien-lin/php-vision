<?php

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;

class CompilerTest extends TestCase
{
    private TemplateParser $parser;
    private TemplateCompiler $compiler;

    protected function setUp(): void
    {
        $this->parser = new TemplateParser();
        $this->compiler = new TemplateCompiler();
    }

    public function testCompileSimpleText(): void
    {
        $content = 'Hello World';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $this->assertStringContainsString('Hello World', $compiled->phpCode);
        $this->assertStringContainsString('<?php', $compiled->phpCode);
        $this->assertStringContainsString('$__output', $compiled->phpCode);
    }

    public function testCompileVariable(): void
    {
        $content = 'Hello {{ name }}';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $this->assertStringContainsString('$__output', $compiled->phpCode);
        $this->assertStringContainsString('resolveVariable', $compiled->phpCode);
        $this->assertStringContainsString('name', $compiled->phpCode);
    }

    public function testCompileForLoop(): void
    {
        $content = '{% for item in items %}{{ item }}{% endfor %}';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $this->assertStringContainsString('foreach', $compiled->phpCode);
        $this->assertStringContainsString('items', $compiled->phpCode);
        $this->assertStringContainsString('item', $compiled->phpCode);
    }

    public function testCompileIfCondition(): void
    {
        $content = '{% if active %}Yes{% endif %}';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $this->assertStringContainsString('if', $compiled->phpCode);
        $this->assertStringContainsString('evaluateCondition', $compiled->phpCode);
        $this->assertStringContainsString('active', $compiled->phpCode);
    }

    public function testCompileIfElseCondition(): void
    {
        $content = '{% if x > 5 %}High{% else %}Low{% endif %}';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $this->assertStringContainsString('if', $compiled->phpCode);
        $this->assertStringContainsString('else', $compiled->phpCode);
        $this->assertStringContainsString('High', $compiled->phpCode);
        $this->assertStringContainsString('Low', $compiled->phpCode);
    }

    public function testCompileIfElseifCondition(): void
    {
        $content = '{% if x > 10 %}High{% elseif x > 5 %}Medium{% else %}Low{% endif %}';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $this->assertStringContainsString('if', $compiled->phpCode);
        $this->assertStringContainsString('elseif', $compiled->phpCode);
        $this->assertStringContainsString('else', $compiled->phpCode);
    }

    public function testCompileVariableWithFilter(): void
    {
        $content = '{{ name | upper }}';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $this->assertStringContainsString('applyFilter', $compiled->phpCode);
        $this->assertStringContainsString('upper', $compiled->phpCode);
    }

    public function testCompileNestedStructures(): void
    {
        $content = '{% for user in users %}{% if user.active %}{{ user.name }}{% endif %}{% endfor %}';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $this->assertStringContainsString('foreach', $compiled->phpCode);
        $this->assertStringContainsString('if', $compiled->phpCode);
        // Vérifier l'indentation (2 niveaux)
        $this->assertStringContainsString('        ', $compiled->phpCode);
    }

    public function testCompileComplexTemplate(): void
    {
        $content = <<<'TPL'
        <h1>{{ title }}</h1>
        {% for item in items %}
            {% if item > 5 %}
                <p>{{ item }}</p>
            {% endif %}
        {% endfor %}
        TPL;

        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        // Vérifier que le code est valide syntaxiquement
        $this->assertStringContainsString('<?php', $compiled->phpCode);
        $this->assertStringContainsString('return implode', $compiled->phpCode);
        
        // Vérifier la présence des éléments clés
        $this->assertStringContainsString('foreach', $compiled->phpCode);
        $this->assertStringContainsString('if', $compiled->phpCode);
        $this->assertStringContainsString('title', $compiled->phpCode);
    }

    public function testCompiledCodeStructure(): void
    {
        $content = 'Test';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        // Vérifier la structure du code généré
        $this->assertStringStartsWith('<?php', $compiled->phpCode);
        $this->assertStringContainsString('$__output = []', $compiled->phpCode);
        $this->assertStringContainsString("return implode('', \$__output)", $compiled->phpCode);
    }

    public function testSaveToFile(): void
    {
        $content = 'Test';
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);

        $tmpFile = sys_get_temp_dir() . '/vision_test_' . uniqid() . '.php';
        
        try {
            $result = $compiled->saveToFile($tmpFile);
            $this->assertTrue($result);
            $this->assertFileExists($tmpFile);
            
            $savedCode = file_get_contents($tmpFile);
            $this->assertEquals($compiled->phpCode, $savedCode);
        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }
}
