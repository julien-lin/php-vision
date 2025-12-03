<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Runtime\Sandbox;
use JulienLinard\Vision\Exception\TemplateNotFoundException;
use JulienLinard\Vision\Exception\VisionException;

class SecurityTest extends TestCase
{
    private Vision $vision;
    private string $templateDir;

    protected function setUp(): void
    {
        $this->templateDir = sys_get_temp_dir() . '/vision_templates_' . uniqid();
        mkdir($this->templateDir, 0755, true);
        $this->vision = new Vision($this->templateDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->templateDir)) {
            $files = glob($this->templateDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($this->templateDir);
        }
    }

    public function testPathTraversalProtection(): void
    {
        $this->expectException(TemplateNotFoundException::class);

        // Tentative de path traversal
        $this->vision->render('../../../etc/passwd', []);
    }

    public function testPathTraversalWithBackslashes(): void
    {
        $this->expectException(TemplateNotFoundException::class);

        // Tentative de path traversal avec backslashes
        $this->vision->render('..\\..\\..\\etc\\passwd', []);
    }

    public function testInvalidFunctionName(): void
    {
        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Invalid function call in template');

        $template = '{{ evil-function("test") }}';
        $this->vision->renderString($template, []);
    }

    public function testInvalidFunctionNameWithSpecialChars(): void
    {
        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Invalid function call in template');

        $template = '{{ func.name("test") }}';
        $this->vision->renderString($template, []);
    }

    public function testValidFunctionName(): void
    {
        $this->vision->registerFunction('valid_function', function ($text) {
            return strtoupper($text);
        });

        $template = '{{ valid_function("hello") }}';
        $result = $this->vision->renderString($template, []);

        $this->assertEquals('HELLO', $result);
    }

    public function testXssProtectionWithAutoEscape(): void
    {
        $template = '{{ content }}';
        $variables = [
            'content' => '<script>alert("XSS")</script><img src=x onerror=alert(1)>',
        ];

        $result = $this->vision->renderString($template, $variables);

        // Vérifier que le HTML dangereux est échappé
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringContainsString('&lt;img', $result);
        // Vérifier que le contenu dangereux est échappé
        $this->assertStringContainsString('&quot;XSS&quot;', $result);
        // onerror sera échappé donc "onerror=" sera présent mais échappé
        $this->assertStringContainsString('onerror', $result);
    }

    public function testXssProtectionWithEscapeFilter(): void
    {
        $vision = new Vision('', false); // Désactiver auto-escape
        $template = '{{ content|escape }}';
        $variables = [
            'content' => '<script>alert("XSS")</script>',
        ];

        $result = $vision->renderString($template, $variables);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testTemplateOutsideAllowedDirectory(): void
    {
        $this->expectException(TemplateNotFoundException::class);

        // Créer un fichier en dehors du répertoire autorisé
        $outsideFile = sys_get_temp_dir() . '/outside_template.php';
        file_put_contents($outsideFile, 'Test');

        try {
            // Essayer d'accéder avec un chemin relatif
            $this->vision->render('../outside_template', []);
        } finally {
            @unlink($outsideFile);
        }
    }

    public function testValidTemplatePath(): void
    {
        // Créer un template valide
        $templateFile = $this->templateDir . '/valid.php';
        file_put_contents($templateFile, 'Hello {{ name }}!');

        $result = $this->vision->render('valid', ['name' => 'World']);

        $this->assertStringContainsString('Hello World!', $result);
    }

    public function testSandboxMode(): void
    {
        $sandbox = new Sandbox();
        $sandbox->setAllowedFunctions(['uppercase']);
        $sandbox->setAllowedFilters(['upper', 'lower']);
        $sandbox->setMaxTemplateSize(1024);
        $sandbox->setMaxRecursionDepth(10);

        $vision = new Vision();
        $vision->setSandbox($sandbox);

        // Template avec fonction autorisée
        $vision->registerFunction('uppercase', fn($s) => strtoupper($s));
        $result = $vision->renderString('{{ uppercase("hello") }}', []);
        $this->assertEquals('HELLO', $result);

        // Template avec fonction non autorisée
        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('not allowed in sandbox mode');
        $vision->renderString('{{ evil_function("test") }}', []);
    }

    public function testSandboxModeStrict(): void
    {
        $sandbox = new Sandbox();
        $sandbox->setStrictMode(true);
        $sandbox->setAllowedFunctions(['uppercase']);

        $vision = new Vision();
        $vision->setSandbox($sandbox);
        $vision->registerFunction('uppercase', fn($s) => strtoupper($s));

        // Fonction autorisée fonctionne
        $result = $vision->renderString('{{ uppercase("hello") }}', []);
        $this->assertEquals('HELLO', $result);

        // Fonction non autorisée échoue
        $this->expectException(VisionException::class);
        $vision->renderString('{{ lowercase("test") }}', []);
    }

    public function testSandboxTemplateSize(): void
    {
        $sandbox = new Sandbox();
        $sandbox->setMaxTemplateSize(100);

        $vision = new Vision();
        $vision->setSandbox($sandbox);

        // Template trop grand
        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Template too large');
        $vision->renderString(str_repeat('x', 200), []);
    }

    public function testSandboxFilters(): void
    {
        $sandbox = new Sandbox();
        $sandbox->setAllowedFilters(['upper', 'lower']);

        $vision = new Vision();
        $vision->setSandbox($sandbox);

        // Filtre autorisé fonctionne
        $result = $vision->renderString('{{ "hello"|upper }}', []);
        $this->assertStringContainsString('HELLO', $result);

        // Filtre non autorisé échoue en mode strict
        $sandbox->setStrictMode(true);
        $sandbox->setAllowedFilters(['upper']); // Seulement upper autorisé
        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Filter');
        $vision->renderString('{{ "hello"|lower }}', []);
    }

    public function testSandboxRecursionDepth(): void
    {
        $sandbox = new Sandbox();
        $sandbox->setMaxRecursionDepth(2);

        $vision = new Vision();
        $vision->setSandbox($sandbox);

        // Template avec récursion trop profonde
        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Profondeur de récursion maximale');
        
        $template = '{% if true %}{% if true %}{% if true %}Deep{% endif %}{% endif %}{% endif %}';
        $vision->renderString($template, []);
    }
}
