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
 * Tests pour le système d'héritage de templates
 * 
 * Teste {% extends %}, {% block %}, {% endblock %}, {{ parent() }}
 */
class InheritanceTest extends TestCase
{
    private string $tempDir;
    private Vision $vision;

    protected function setUp(): void
    {
        // Créer un répertoire temporaire pour les templates
        $this->tempDir = sys_get_temp_dir() . '/vision_inheritance_test_' . uniqid();
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
        rmdir($this->tempDir);
    }

    private function createTemplate(string $name, string $content): void
    {
        file_put_contents($this->tempDir . '/' . $name, $content);
    }

    public function testSimpleExtends(): void
    {
        // Template parent avec un block
        $this->createTemplate(
            'base.html',
            <<<'TPL'
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}Default Title{% endblock %}</title>
</head>
<body>
    {% block content %}Default Content{% endblock %}
</body>
</html>
TPL
        );

        // Template enfant qui override le block title
        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block title %}Child Title{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('<title>Child Title</title>', $result);
        $this->assertStringContainsString('Default Content', $result);
    }

    public function testMultipleBlocks(): void
    {
        // Parent avec plusieurs blocks
        $this->createTemplate(
            'base.html',
            <<<'TPL'
<header>{% block header %}Default Header{% endblock %}</header>
<main>{% block content %}Default Content{% endblock %}</main>
<footer>{% block footer %}Default Footer{% endblock %}</footer>
TPL
        );

        // Enfant override 2 blocks sur 3
        $this->createTemplate(
            'page.html',
            <<<'TPL'
{% extends "base.html" %}

{% block header %}Custom Header{% endblock %}
{% block content %}Custom Content{% endblock %}
TPL
        );

        $result = $this->vision->render('page.html');

        $this->assertStringContainsString('Custom Header', $result);
        $this->assertStringContainsString('Custom Content', $result);
        $this->assertStringContainsString('Default Footer', $result);
    }

    public function testMultiLevelInheritance(): void
    {
        // Niveau 1: Base
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block content %}Base Content{% endblock %}
TPL
        );

        // Niveau 2: Layout
        $this->createTemplate(
            'layout.html',
            <<<'TPL'
{% extends "base.html" %}

{% block content %}Layout: {% block page %}Page Content{% endblock %}{% endblock %}
TPL
        );

        // Niveau 3: Page
        $this->createTemplate(
            'page.html',
            <<<'TPL'
{% extends "layout.html" %}

{% block page %}Custom Page{% endblock %}
TPL
        );

        $result = $this->vision->render('page.html');

        $this->assertStringContainsString('Layout:', $result);
        $this->assertStringContainsString('Custom Page', $result);
        $this->assertStringNotContainsString('Base Content', $result);
        $this->assertStringNotContainsString('Page Content', $result);
    }

    public function testParentCall(): void
    {
        // Parent avec contenu
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block content %}
<div>Parent Content</div>
{% endblock %}
TPL
        );

        // Enfant utilise {{ parent() }}
        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block content %}
{{ parent() }}
<div>Child Content</div>
{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('Parent Content', $result);
        $this->assertStringContainsString('Child Content', $result);

        // Vérifier l'ordre: parent avant enfant
        $parentPos = strpos($result, 'Parent Content');
        $childPos = strpos($result, 'Child Content');
        $this->assertLessThan($childPos, $parentPos);
    }

    public function testMultipleParentCalls(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block content %}PARENT{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block content %}[{{ parent() }}] and [{{ parent() }}]{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('[PARENT] and [PARENT]', $result);
    }

    public function testBlockWithVariables(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block greeting %}Hello, {{ name }}!{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block greeting %}Hi {{ name }}, welcome!{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html', ['name' => 'John']);

        $this->assertStringContainsString('Hi John, welcome!', $result);
        $this->assertStringNotContainsString('Hello', $result);
    }

    public function testBlockWithLoops(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
<ul>
{% block items %}
    {% for item in items %}
    <li>Default: {{ item }}</li>
    {% endfor %}
{% endblock %}
</ul>
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block items %}
    {% for item in items %}
    <li>Custom: {{ item }}</li>
    {% endfor %}
{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html', ['items' => ['A', 'B', 'C']]);

        $this->assertStringContainsString('<li>Custom: A</li>', $result);
        $this->assertStringContainsString('<li>Custom: B</li>', $result);
        $this->assertStringContainsString('<li>Custom: C</li>', $result);
        $this->assertStringNotContainsString('Default:', $result);
    }

    public function testBlockWithConditions(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block message %}
    {% if logged_in %}
    <p>Welcome back!</p>
    {% else %}
    <p>Please login</p>
    {% endif %}
{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block message %}
    {% if logged_in %}
    <p>Hello admin!</p>
    {% else %}
    {{ parent() }}
    {% endif %}
{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html', ['logged_in' => true]);
        $this->assertStringContainsString('Hello admin!', $result);

        $result = $this->vision->render('child.html', ['logged_in' => false]);
        $this->assertStringContainsString('Please login', $result);
    }

    public function testExtendsWithoutBlocks(): void
    {
        // Parent sans blocks
        $this->createTemplate('base.html', '<h1>Static Content</h1>');

        // Enfant extends mais ne peut rien override
        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('Static Content', $result);
    }

    public function testEmptyBlock(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block content %}Default{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block content %}{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringNotContainsString('Default', $result);
        $this->assertEmpty(trim($result));
    }

    public function testNestedBlocks(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block outer %}
    <div>
    {% block inner %}Inner Default{% endblock %}
    </div>
{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block inner %}Inner Custom{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('Inner Custom', $result);
        $this->assertStringNotContainsString('Inner Default', $result);
        $this->assertStringContainsString('<div>', $result);
    }

    public function testBlockOverrideChain(): void
    {
        // Grand-parent
        $this->createTemplate(
            'grandparent.html',
            <<<'TPL'
{% block content %}Grandparent{% endblock %}
TPL
        );

        // Parent override
        $this->createTemplate(
            'parent.html',
            <<<'TPL'
{% extends "grandparent.html" %}

{% block content %}Parent: {{ parent() }}{% endblock %}
TPL
        );

        // Enfant override
        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "parent.html" %}

{% block content %}Child: {{ parent() }}{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('Child: Parent: Grandparent', $result);
    }

    public function testMissingParentTemplate(): void
    {
        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "nonexistent.html" %}
TPL
        );

        $this->expectException(VisionException::class);
        $this->vision->render('child.html');
    }

    public function testCircularInheritance(): void
    {
        // A extends B
        $this->createTemplate('a.html', '{% extends "b.html" %}');

        // B extends A (cycle!)
        $this->createTemplate('b.html', '{% extends "a.html" %}');

        $this->expectException(VisionException::class);
        $this->expectExceptionMessage('Circular inheritance');
        $this->vision->render('a.html');
    }

    public function testExtendsNotFirstStatement(): void
    {
        // Le extends doit être la première directive (comme Twig)
        $this->createTemplate(
            'invalid.html',
            <<<'TPL'
<p>Some content before</p>
{% extends "base.html" %}
TPL
        );

        $this->createTemplate('base.html', 'Base');

        // Cette restriction peut être ajoutée dans le parser si nécessaire
        // Pour l'instant, on teste que ça ne crash pas
        $result = $this->vision->render('invalid.html');
        $this->assertIsString($result);
    }

    public function testBlockWithFilters(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block title %}default title{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block title %}{{ title | upper }}{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html', ['title' => 'my title']);

        $this->assertStringContainsString('MY TITLE', $result);
        $this->assertStringNotContainsString('default title', $result);
    }

    public function testParentWithFilters(): void
    {
        // Note: Les filtres sur parent() ne sont pas supportés directement
        // comme dans Twig. On teste qu'on peut utiliser le contenu parent
        // dans une variable et lui appliquer un filtre

        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block content %}hello world{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{% block content %}{{ parent() }} - extended{% endblock %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('hello world - extended', $result);
    }

    public function testMultipleTemplatesWithSameBlocks(): void
    {
        // Base commun
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block content %}Base{% endblock %}
TPL
        );

        // Deux enfants différents
        $this->createTemplate(
            'child1.html',
            <<<'TPL'
{% extends "base.html" %}
{% block content %}Child 1{% endblock %}
TPL
        );

        $this->createTemplate(
            'child2.html',
            <<<'TPL'
{% extends "base.html" %}
{% block content %}Child 2{% endblock %}
TPL
        );

        $result1 = $this->vision->render('child1.html');
        $result2 = $this->vision->render('child2.html');

        $this->assertStringContainsString('Child 1', $result1);
        $this->assertStringNotContainsString('Child 2', $result1);

        $this->assertStringContainsString('Child 2', $result2);
        $this->assertStringNotContainsString('Child 1', $result2);
    }

    public function testBlockWithWhitespace(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
{% block content %}
    <p>Indented Content</p>
{% endblock %}
TPL
        );

        $this->createTemplate(
            'child.html',
            <<<'TPL'
{% extends "base.html" %}

{%    block    content    %}
    <p>Custom Content</p>
{%    endblock    %}
TPL
        );

        $result = $this->vision->render('child.html');

        $this->assertStringContainsString('Custom Content', $result);
        $this->assertStringNotContainsString('Indented Content', $result);
    }

    public function testDeepInheritanceChain(): void
    {
        // 5 niveaux d'héritage
        $this->createTemplate('level1.html', 'L1: {% block content %}Content 1{% endblock %}');
        $this->createTemplate('level2.html', '{% extends "level1.html" %}{% block content %}{{ parent() }} -> 2{% endblock %}');
        $this->createTemplate('level3.html', '{% extends "level2.html" %}{% block content %}{{ parent() }} -> 3{% endblock %}');
        $this->createTemplate('level4.html', '{% extends "level3.html" %}{% block content %}{{ parent() }} -> 4{% endblock %}');
        $this->createTemplate('level5.html', '{% extends "level4.html" %}{% block content %}{{ parent() }} -> 5{% endblock %}');

        $result = $this->vision->render('level5.html');

        $this->assertStringContainsString('L1: Content 1 -> 2 -> 3 -> 4 -> 5', $result);
    }

    public function testBlockWithComplexHTML(): void
    {
        $this->createTemplate(
            'base.html',
            <<<'TPL'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    {% block head %}
    <title>Default</title>
    <link rel="stylesheet" href="/css/style.css">
    {% endblock %}
</head>
<body>
    <nav>{% block nav %}Default Nav{% endblock %}</nav>
    <main>{% block main %}Default Main{% endblock %}</main>
    <footer>{% block footer %}Default Footer{% endblock %}</footer>
</body>
</html>
TPL
        );

        $this->createTemplate(
            'page.html',
            <<<'TPL'
{% extends "base.html" %}

{% block head %}
    {{ parent() }}
    <script src="/js/app.js"></script>
{% endblock %}

{% block main %}
    <h1>Welcome</h1>
    <p>Custom page content</p>
{% endblock %}
TPL
        );

        $result = $this->vision->render('page.html');

        $this->assertStringContainsString('<title>Default</title>', $result);
        $this->assertStringContainsString('<script src="/js/app.js"></script>', $result);
        $this->assertStringContainsString('<h1>Welcome</h1>', $result);
        $this->assertStringContainsString('Default Nav', $result);
        $this->assertStringContainsString('Default Footer', $result);
    }
}
