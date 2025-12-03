<?php

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Compiler\CompilationRateLimiter;
use JulienLinard\Vision\Exception\VisionException;

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

    public function testCompilationRateLimiter(): void
    {
        $rateLimiter = new CompilationRateLimiter(3, 60); // 3 tentatives max par 60 secondes
        $templatePath = '/path/to/template.vis';
        
        // Premières tentatives doivent passer
        $this->assertTrue($rateLimiter->checkLimit($templatePath));
        $this->assertEquals(2, $rateLimiter->getRemainingAttempts($templatePath));
        
        $this->assertTrue($rateLimiter->checkLimit($templatePath));
        $this->assertEquals(1, $rateLimiter->getRemainingAttempts($templatePath));
        
        $this->assertTrue($rateLimiter->checkLimit($templatePath));
        $this->assertEquals(0, $rateLimiter->getRemainingAttempts($templatePath));
        
        // La quatrième tentative doit échouer
        $this->assertFalse($rateLimiter->checkLimit($templatePath));
        $this->assertEquals(0, $rateLimiter->getRemainingAttempts($templatePath));
    }

    public function testCompilationRateLimiterDisabled(): void
    {
        $rateLimiter = new CompilationRateLimiter(3, 60);
        $rateLimiter->setEnabled(false);
        $templatePath = '/path/to/template.vis';
        
        // Toutes les tentatives doivent passer si désactivé
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($rateLimiter->checkLimit($templatePath));
        }
    }

    public function testCompilationRateLimiterReset(): void
    {
        $rateLimiter = new CompilationRateLimiter(3, 60);
        $templatePath = '/path/to/template.vis';
        
        // Atteindre la limite
        $rateLimiter->checkLimit($templatePath);
        $rateLimiter->checkLimit($templatePath);
        $rateLimiter->checkLimit($templatePath);
        $this->assertFalse($rateLimiter->checkLimit($templatePath));
        
        // Réinitialiser
        $rateLimiter->reset($templatePath);
        
        // Devrait pouvoir compiler à nouveau
        $this->assertTrue($rateLimiter->checkLimit($templatePath));
        $this->assertEquals(2, $rateLimiter->getRemainingAttempts($templatePath));
    }

    public function testCompilationRateLimiterWithCompiler(): void
    {
        $rateLimiter = new CompilationRateLimiter(2, 60); // 2 tentatives max
        $compiler = new TemplateCompiler();
        $compiler->setRateLimiter($rateLimiter);
        
        $parser = new TemplateParser();
        $templatePath = sys_get_temp_dir() . '/test_rate_limit_' . uniqid() . '.vis';
        
        try {
            file_put_contents($templatePath, 'Hello {{ name }}!');
            
            $parsed = $parser->parse(file_get_contents($templatePath));
            
            // Première compilation doit passer
            $compiled1 = $compiler->compile($parsed, $templatePath);
            $this->assertNotNull($compiled1);
            
            // Deuxième compilation doit passer
            $compiled2 = $compiler->compile($parsed, $templatePath);
            $this->assertNotNull($compiled2);
            
            // Troisième compilation doit échouer
            $this->expectException(VisionException::class);
            $this->expectExceptionMessage('Rate limit atteint');
            $compiler->compile($parsed, $templatePath);
        } finally {
            @unlink($templatePath);
        }
    }

    public function testCompilationRateLimiterStats(): void
    {
        $rateLimiter = new CompilationRateLimiter(5, 120);
        
        $stats = $rateLimiter->getStats();
        $this->assertTrue($stats['enabled']);
        $this->assertEquals(5, $stats['max_attempts']);
        $this->assertEquals(120, $stats['window_seconds']);
        $this->assertEquals(0, $stats['tracked_templates']);
        
        // Utiliser le rate limiter
        $rateLimiter->checkLimit('/path/to/template1.vis');
        $rateLimiter->checkLimit('/path/to/template2.vis');
        
        $stats = $rateLimiter->getStats();
        $this->assertEquals(2, $stats['tracked_templates']);
    }

    public function testCompilationRateLimiterWaitTime(): void
    {
        $rateLimiter = new CompilationRateLimiter(2, 10); // 2 tentatives max par 10 secondes
        $templatePath = '/path/to/template.vis';
        
        // Premières tentatives
        $rateLimiter->checkLimit($templatePath);
        $rateLimiter->checkLimit($templatePath);
        
        // Atteindre la limite
        $this->assertFalse($rateLimiter->checkLimit($templatePath));
        
        // Vérifier le temps d'attente
        $waitTime = $rateLimiter->getWaitTime($templatePath);
        $this->assertGreaterThan(0, $waitTime);
        $this->assertLessThanOrEqual(10, $waitTime);
    }
}
