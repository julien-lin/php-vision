<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;
use JulienLinard\Vision\Exception\VisionException;
use PHPUnit\Framework\TestCase;

/**
 * Tests pour le système de macros
 * 
 * Teste {% macro %}, {% import %}, appels de macros
 */
class MacroTest extends TestCase
{
    private string $tempDir;
    private Vision $vision;

    protected function setUp(): void
    {
        // Créer un répertoire temporaire pour les templates
        $this->tempDir = sys_get_temp_dir() . '/vision_macro_test_' . uniqid();
        mkdir($this->tempDir);

        // Créer un répertoire pour le cache
        $cacheDir = $this->tempDir . '/cache';
        mkdir($cacheDir);

        // Initialiser Vision avec Parser, Compiler et CacheManager
        $this->vision = new Vision($this->tempDir);
        $parser = new TemplateParser();
        $compiler = new TemplateCompiler();
        $cacheManager = new CacheManager($cacheDir);

        $this->vision->setParser($parser);
        $this->vision->setCompiler($compiler);
        $this->vision->setCacheManager($cacheManager);

        // Activer le cache pour utiliser le pipeline compilé
        $this->vision->setCache(true, $cacheDir);
    }

    protected function tearDown(): void
    {
        // Nettoyer les fichiers temporaires
        $files = glob($this->tempDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $cacheFiles = glob($this->tempDir . '/cache/*');
        if ($cacheFiles) {
            foreach ($cacheFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($this->tempDir . '/cache')) {
            rmdir($this->tempDir . '/cache');
        }
        rmdir($this->tempDir);
    }

    private function createTemplate(string $name, string $content): void
    {
        file_put_contents($this->tempDir . '/' . $name, $content);
    }

    public function testSimpleMacro(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro hello(name) %}
Hello, {{ name }}!
{% endmacro %}

{{ hello('World') }}
TPL
        );

        $result = $this->vision->render('test.html');

        $this->assertStringContainsString('Hello, World!', $result);
    }

    public function testMacroWithDefaultValue(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro greet(name, greeting="Hi") %}
{{ greeting }}, {{ name }}!
{% endmacro %}

{{ greet('Alice') }}
{{ greet('Bob', 'Hello') }}
TPL
        );

        $result = $this->vision->render('test.html');

        $this->assertStringContainsString('Hi, Alice!', $result);
        $this->assertStringContainsString('Hello, Bob!', $result);
    }

    public function testMacroWithMultipleParameters(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro input(name, value, type="text") %}
<input type="{{ type }}" name="{{ name }}" value="{{ value }}">
{% endmacro %}

{{ input('email', 'user@example.com', 'email') }}
{{ input('username', 'john') }}
TPL
        );

        $result = $this->vision->render('test.html');

        $this->assertStringContainsString('<input type="email" name="email" value="user@example.com">', $result);
        $this->assertStringContainsString('<input type="text" name="username" value="john">', $result);
    }

    public function testMacroWithVariableArguments(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro greet(name) %}
Hello, {{ name }}!
{% endmacro %}

{{ greet(username) }}
TPL
        );

        $result = $this->vision->render('test.html', ['username' => 'Alice']);

        $this->assertStringContainsString('Hello, Alice!', $result);
    }

    public function testMultipleMacros(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro header(title) %}
<h1>{{ title }}</h1>
{% endmacro %}

{% macro footer(year) %}
<footer>© {{ year }}</footer>
{% endmacro %}

{{ header('Welcome') }}
<main>Content</main>
{{ footer('2025') }}
TPL
        );

        $result = $this->vision->render('test.html');

        $this->assertStringContainsString('<h1>Welcome</h1>', $result);
        $this->assertStringContainsString('<footer>© 2025</footer>', $result);
        $this->assertStringContainsString('<main>Content</main>', $result);
    }

    public function testMacroImport(): void
    {
        // Créer un fichier de macros
        $this->createTemplate(
            'macros.html',
            <<<'TPL'
{% macro button(label, type="button") %}
<button type="{{ type }}">{{ label }}</button>
{% endmacro %}

{% macro link(text, url) %}
<a href="{{ url }}">{{ text }}</a>
{% endmacro %}
TPL
        );

        // Template qui importe les macros
        $this->createTemplate(
            'page.html',
            <<<'TPL'
{% import "macros.html" as ui %}

{{ ui.button('Click me', 'submit') }}
{{ ui.link('Home', '/home') }}
TPL
        );

        $result = $this->vision->render('page.html');

        $this->assertStringContainsString('<button type="submit">Click me</button>', $result);
        $this->assertStringContainsString('<a href="/home">Home</a>', $result);
    }

    public function testMacroWithHtmlContent(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro card(title, content) %}
<div class="card">
    <h2>{{ title }}</h2>
    <p>{{ content }}</p>
</div>
{% endmacro %}

{{ card('Welcome', 'This is a card') }}
TPL
        );

        $result = $this->vision->render('test.html');

        $this->assertStringContainsString('<div class="card">', $result);
        $this->assertStringContainsString('<h2>Welcome</h2>', $result);
        $this->assertStringContainsString('<p>This is a card</p>', $result);
    }

    public function testMacroWithLoop(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro list(items) %}
<ul>
{% for item in items %}
    <li>{{ item }}</li>
{% endfor %}
</ul>
{% endmacro %}

{{ list(fruits) }}
TPL
        );

        $result = $this->vision->render('test.html', [
            'fruits' => ['Apple', 'Banana', 'Cherry']
        ]);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>Apple</li>', $result);
        $this->assertStringContainsString('<li>Banana</li>', $result);
        $this->assertStringContainsString('<li>Cherry</li>', $result);
    }

    public function testMacroWithCondition(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro badge(status) %}
{% if status == "active" %}
    <span class="badge-success">Active</span>
{% else %}
    <span class="badge-danger">Inactive</span>
{% endif %}
{% endmacro %}

{{ badge('active') }}
{{ badge('inactive') }}
TPL
        );

        $result = $this->vision->render('test.html');

        $this->assertStringContainsString('<span class="badge-success">Active</span>', $result);
        $this->assertStringContainsString('<span class="badge-danger">Inactive</span>', $result);
    }

    public function testMacroNotDefinedError(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{{ nonexistent('test') }}
TPL
        );

        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Undefined macro');
        $this->vision->render('test.html');
    }

    public function testMacroMissingRequiredParameter(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro greet(name, message) %}
{{ message }}, {{ name }}!
{% endmacro %}

{{ greet('Alice') }}
TPL
        );

        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Missing required parameter');
        $this->vision->render('test.html');
    }

    public function testImportNonexistentTemplate(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% import "nonexistent.html" as ui %}
TPL
        );

        $this->expectException(\Exception::class);
        $this->vision->render('test.html');
    }

    public function testMacroWithFilters(): void
    {
        $this->createTemplate(
            'test.html',
            <<<'TPL'
{% macro title(text) %}
<h1>{{ text | upper }}</h1>
{% endmacro %}

{{ title('hello world') }}
TPL
        );

        $result = $this->vision->render('test.html');

        $this->assertStringContainsString('<h1>HELLO WORLD</h1>', $result);
    }

    public function testNestedMacroCallsNotSupported(): void
    {
        // Les macros ne peuvent pas appeler d'autres macros dans leur corps
        // Cette limitation est documentée
        $this->markTestSkipped('Nested macro calls not yet supported');
    }

    public function testMacroInInheritance(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% macro button(label) %}
<button>{{ label }}</button>
{% endmacro %}

{% block content %}{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block content %}
{{ button('Click') }}
{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('<button>Click</button>', $result);
    }

    public function testImportWithMultipleMacros(): void
    {
        $this->createTemplate(
            'forms.html',
            <<<'TPL'
{% macro input(name, value) %}
<input name="{{ name }}" value="{{ value }}">
{% endmacro %}

{% macro textarea(name, value) %}
<textarea name="{{ name }}">{{ value }}</textarea>
{% endmacro %}

{% macro select(name, options) %}
<select name="{{ name }}">
{% for option in options %}
    <option>{{ option }}</option>
{% endfor %}
</select>
{% endmacro %}
TPL
        );

        $this->createTemplate(
            'page.html',
            <<<'TPL'
{% import "forms.html" as forms %}

{{ forms.input('username', 'john') }}
{{ forms.textarea('bio', 'Hello') }}
{{ forms.select('country', countries) }}
TPL
        );

        $result = $this->vision->render('page.html', [
            'countries' => ['France', 'USA', 'UK']
        ]);

        $this->assertStringContainsString('<input name="username" value="john">', $result);
        $this->assertStringContainsString('<textarea name="bio">Hello</textarea>', $result);
        $this->assertStringContainsString('<select name="country">', $result);
        $this->assertStringContainsString('<option>France</option>', $result);
    }
}
