<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use JulienLinard\Vision\Vision;
use PHPUnit\Framework\TestCase;

class ComponentTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        mkdir($this->tempDir . '/components', 0755, true);
        mkdir($this->tempDir . '/partials', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testComponentFunction(): void
    {
        // Créer un composant Button
        file_put_contents(
            $this->tempDir . '/components/Button.html.vis',
            '<button class="btn btn-{{ variant }}">{{ label }}</button>'
        );

        $vision = new Vision($this->tempDir);
        $html = $vision->renderString(
            '{{ component("Button", buttonProps) }}',
            [
                'buttonProps' => [
                    'label' => 'Save',
                    'variant' => 'primary'
                ]
            ]
        );

        $this->assertStringContainsString('btn-primary', $html);
        $this->assertStringContainsString('Save', $html);
    }

    public function testComponentWithConventionPath(): void
    {
        // Sans chemin explicite, cherche dans components/
        file_put_contents(
            $this->tempDir . '/components/Card.html.vis',
            '<div class="card"><h3>{{ title }}</h3><p>{{ content }}</p></div>'
        );

        $vision = new Vision($this->tempDir);
        $html = $vision->renderString(
            '{{ component("Card", cardData) }}',
            [
                'cardData' => [
                    'title' => 'Hello',
                    'content' => 'World'
                ]
            ]
        );

        $this->assertStringContainsString('<h3>Hello</h3>', $html);
        $this->assertStringContainsString('<p>World</p>', $html);
    }

    public function testComponentWithExplicitPath(): void
    {
        // Avec chemin explicite, pas de convention
        mkdir($this->tempDir . '/widgets', 0755, true);
        file_put_contents(
            $this->tempDir . '/widgets/Alert.html.vis',
            '<div class="alert alert-{{ type }}">{{ message }}</div>'
        );

        $vision = new Vision($this->tempDir);
        $html = $vision->renderString(
            '{{ component("widgets/Alert", alertData) }}',
            [
                'alertData' => [
                    'type' => 'success',
                    'message' => 'Operation successful!'
                ]
            ]
        );

        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringContainsString('Operation successful!', $html);
    }

    public function testNestedComponents(): void
    {
        // Composant parent
        file_put_contents(
            $this->tempDir . '/components/Panel.html.vis',
            '<div class="panel">{{ component("PanelHeader", header) }}<div class="body">{{ body }}</div></div>'
        );

        // Composant enfant
        file_put_contents(
            $this->tempDir . '/components/PanelHeader.html.vis',
            '<div class="panel-header"><h2>{{ title }}</h2></div>'
        );

        $vision = new Vision($this->tempDir);
        $html = $vision->renderString(
            '{{ component("Panel", panelData) }}',
            [
                'panelData' => [
                    'header' => ['title' => 'Dashboard'],
                    'body' => 'Content here'
                ]
            ]
        );

        $this->assertStringContainsString('<h2>Dashboard</h2>', $html);
        $this->assertStringContainsString('Content here', $html);
    }

    public function testSlotFunction(): void
    {
        // slot() permet de passer du contenu pré-rendu
        file_put_contents(
            $this->tempDir . '/components/Layout.html.vis',
            '<html><head>{{ slot("head", head) }}</head><body>{{ slot("content", content) }}</body></html>'
        );

        $vision = new Vision($this->tempDir);

        // Pré-rendre les slots
        $headContent = '<title>My Page</title>';
        $bodyContent = '<h1>Hello World</h1>';

        $html = $vision->renderString(
            '{{ component("Layout", layoutData) }}',
            [
                'layoutData' => [
                    'head' => $headContent,
                    'content' => $bodyContent
                ]
            ]
        );

        $this->assertStringContainsString('<title>My Page</title>', $html);
        $this->assertStringContainsString('<h1>Hello World</h1>', $html);
    }

    public function testSlotWithTemplateFunction(): void
    {
        // Utiliser template() pour générer le contenu d'un slot via les fonctions
        file_put_contents(
            $this->tempDir . '/components/Layout.html.vis',
            '<html><body><header>{{ template("partials/header", headerData) }}</header><main>{{ template("partials/content", contentData) }}</main></body></html>'
        );

        file_put_contents(
            $this->tempDir . '/partials/header.html.vis',
            '<h1>{{ title }}</h1>'
        );

        file_put_contents(
            $this->tempDir . '/partials/content.html.vis',
            '<p>{{ text }}</p>'
        );

        $vision = new Vision($this->tempDir);

        // Passer les données pour chaque slot
        $html = $vision->render('components/Layout', [
            'headerData' => ['title' => 'Welcome'],
            'contentData' => ['text' => 'Hello from slot!']
        ]);

        $this->assertStringContainsString('<h1>Welcome</h1>', $html);
        $this->assertStringContainsString('<p>Hello from slot!</p>', $html);
    }

    public function testMultipleComponentsInSamePage(): void
    {
        file_put_contents(
            $this->tempDir . '/components/Button.html.vis',
            '<button>{{ label }}</button>'
        );

        file_put_contents(
            $this->tempDir . '/components/Link.html.vis',
            '<a href="{{ url }}">{{ text }}</a>'
        );

        $vision = new Vision($this->tempDir);
        $html = $vision->renderString(
            '{{ component("Button", btn1) }} {{ component("Link", link1) }} {{ component("Button", btn2) }}',
            [
                'btn1' => ['label' => 'Save'],
                'link1' => ['url' => '/home', 'text' => 'Home'],
                'btn2' => ['label' => 'Cancel']
            ]
        );

        $this->assertStringContainsString('<button>Save</button>', $html);
        $this->assertStringContainsString('<a href="/home">Home</a>', $html);
        $this->assertStringContainsString('<button>Cancel</button>', $html);
    }
}
