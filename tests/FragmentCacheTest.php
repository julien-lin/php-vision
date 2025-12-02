<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Cache\FragmentCache;
use PHPUnit\Framework\TestCase;

class FragmentCacheTest extends TestCase
{
    private string $tempDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_fragment_test_' . uniqid();
        $this->cacheDir = $this->tempDir . '/cache/fragments';
        mkdir($this->tempDir, 0755, true);
        mkdir($this->tempDir . '/components', 0755, true);
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

    public function testFragmentCacheBasicSetGet(): void
    {
        $cache = new FragmentCache($this->cacheDir, 3600, true);

        $key = 'test_fragment';
        $content = '<div>Cached content</div>';

        // Set et get
        $this->assertTrue($cache->set($key, $content));
        $this->assertEquals($content, $cache->get($key));
    }

    public function testFragmentCacheExpiration(): void
    {
        $cache = new FragmentCache($this->cacheDir, 1, true); // TTL 1 seconde

        $key = 'expiring_fragment';
        $content = '<div>Will expire</div>';

        $cache->set($key, $content);
        $this->assertEquals($content, $cache->get($key));

        // Attendre expiration
        sleep(2);

        $this->assertNull($cache->get($key), 'Fragment should be expired');
    }

    public function testFragmentCacheDisabled(): void
    {
        $cache = new FragmentCache($this->cacheDir, 3600, false);

        $key = 'disabled_fragment';
        $content = '<div>Should not cache</div>';

        // Avec cache désactivé
        $this->assertFalse($cache->set($key, $content));
        $this->assertNull($cache->get($key));
    }

    public function testFragmentCacheGenerateKey(): void
    {
        $cache = new FragmentCache($this->cacheDir, 3600, true);

        $key1 = $cache->generateKey('Button', ['label' => 'Save', 'variant' => 'primary']);
        $key2 = $cache->generateKey('Button', ['label' => 'Save', 'variant' => 'primary']);
        $key3 = $cache->generateKey('Button', ['label' => 'Cancel', 'variant' => 'secondary']);

        // Mêmes props = même clé
        $this->assertEquals($key1, $key2);

        // Props différentes = clés différentes
        $this->assertNotEquals($key1, $key3);
    }

    public function testFragmentCacheInvalidate(): void
    {
        $cache = new FragmentCache($this->cacheDir, 3600, true);

        $key = 'invalidate_test';
        $content = '<div>To invalidate</div>';

        $cache->set($key, $content);
        $this->assertEquals($content, $cache->get($key));

        // Invalider
        $this->assertTrue($cache->invalidate($key));
        $this->assertNull($cache->get($key));
    }

    public function testFragmentCacheInvalidateComponent(): void
    {
        $cache = new FragmentCache($this->cacheDir, 3600, true);

        // Créer plusieurs fragments pour le même composant
        $key1 = $cache->generateKey('Button', ['label' => 'Save']);
        $key2 = $cache->generateKey('Button', ['label' => 'Cancel']);
        $key3 = $cache->generateKey('Card', ['title' => 'Test']);

        $cache->set($key1, '<button>Save</button>');
        $cache->set($key2, '<button>Cancel</button>');
        $cache->set($key3, '<div>Card</div>');

        // Invalider tous les fragments du composant Button
        $deleted = $cache->invalidateComponent('Button');

        $this->assertEquals(2, $deleted, 'Should delete 2 Button fragments');
        $this->assertNull($cache->get($key1));
        $this->assertNull($cache->get($key2));
        $this->assertNotNull($cache->get($key3), 'Card fragment should remain');
    }

    public function testFragmentCacheClearExpired(): void
    {
        $cache = new FragmentCache($this->cacheDir, 1, true); // TTL 1 seconde

        $cache->set('fragment1', 'content1');
        $cache->set('fragment2', 'content2');

        sleep(2); // Attendre expiration

        $deleted = $cache->clearExpired();
        $this->assertEquals(2, $deleted, 'Should clear 2 expired fragments');
    }

    public function testFragmentCacheClearAll(): void
    {
        $cache = new FragmentCache($this->cacheDir, 3600, true);

        $cache->set('fragment1', 'content1');
        $cache->set('fragment2', 'content2');
        $cache->set('fragment3', 'content3');

        $deleted = $cache->clearAll();
        $this->assertEquals(3, $deleted, 'Should clear all 3 fragments');

        $this->assertNull($cache->get('fragment1'));
        $this->assertNull($cache->get('fragment2'));
        $this->assertNull($cache->get('fragment3'));
    }

    public function testFragmentCacheStats(): void
    {
        $cache = new FragmentCache($this->cacheDir, 3600, true);

        $cache->set('frag1', 'content1');
        $cache->set('frag2', 'longer content here');

        $stats = $cache->getStats();

        $this->assertEquals(2, $stats['total']);
        $this->assertGreaterThan(0, $stats['size']);
        $this->assertGreaterThan(0, $stats['oldest']);
    }

    public function testComponentWithFragmentCache(): void
    {
        // Créer un composant
        file_put_contents(
            $this->tempDir . '/components/Button.html.vis',
            '<button class="btn btn-{{ variant }}">{{ label }}</button>'
        );

        $vision = new Vision($this->tempDir);
        $vision->setFragmentCacheConfig(true, $this->cacheDir, 3600);

        // Premier rendu: pas de cache
        $html1 = $vision->renderString(
            '{{ component("Button", buttonProps) }}',
            ['buttonProps' => ['label' => 'Save', 'variant' => 'primary']]
        );

        $this->assertStringContainsString('btn-primary', $html1);
        $this->assertStringContainsString('Save', $html1);

        // Vérifier que le fragment est en cache
        $fragmentCache = $vision->getFragmentCache();
        $this->assertNotNull($fragmentCache);

        $key = $fragmentCache->generateKey('components/Button', ['label' => 'Save', 'variant' => 'primary']);
        $cached = $fragmentCache->get($key);
        $this->assertNotNull($cached, 'Component should be cached');

        // Deuxième rendu: utilise le cache
        $html2 = $vision->renderString(
            '{{ component("Button", buttonProps) }}',
            ['buttonProps' => ['label' => 'Save', 'variant' => 'primary']]
        );

        $this->assertEquals($html1, $html2, 'Cached render should match first render');
    }

    public function testComponentCacheDifferentProps(): void
    {
        file_put_contents(
            $this->tempDir . '/components/Button.html.vis',
            '<button>{{ label }}</button>'
        );

        $vision = new Vision($this->tempDir);
        $vision->setFragmentCacheConfig(true, $this->cacheDir, 3600);

        // Deux rendus avec props différentes
        $html1 = $vision->renderString(
            '{{ component("Button", props1) }}',
            ['props1' => ['label' => 'Save']]
        );

        $html2 = $vision->renderString(
            '{{ component("Button", props2) }}',
            ['props2' => ['label' => 'Cancel']]
        );

        $this->assertStringContainsString('Save', $html1);
        $this->assertStringContainsString('Cancel', $html2);
        $this->assertNotEquals($html1, $html2, 'Different props should produce different output');

        // Vérifier que deux entrées de cache existent
        $fragmentCache = $vision->getFragmentCache();
        $stats = $fragmentCache->getStats();
        $this->assertEquals(2, $stats['total'], 'Should have 2 cached fragments');
    }

    public function testComponentCacheDisabled(): void
    {
        file_put_contents(
            $this->tempDir . '/components/Button.html.vis',
            '<button>{{ label }}</button>'
        );

        $vision = new Vision($this->tempDir);
        // Ne pas activer le fragment cache

        $html = $vision->renderString(
            '{{ component("Button", props) }}',
            ['props' => ['label' => 'Test']]
        );

        $this->assertStringContainsString('Test', $html);

        // Vérifier qu'aucun cache n'a été créé
        $this->assertNull($vision->getFragmentCache());
    }

    public function testNestedComponentsWithCache(): void
    {
        // Composant parent
        file_put_contents(
            $this->tempDir . '/components/Card.html.vis',
            '<div class="card">{{ component("CardHeader", header) }}<div class="body">{{ body }}</div></div>'
        );

        // Composant enfant
        file_put_contents(
            $this->tempDir . '/components/CardHeader.html.vis',
            '<div class="header"><h3>{{ title }}</h3></div>'
        );

        $vision = new Vision($this->tempDir);
        $vision->setFragmentCacheConfig(true, $this->cacheDir, 3600);

        $html = $vision->renderString(
            '{{ component("Card", cardData) }}',
            [
                'cardData' => [
                    'header' => ['title' => 'Dashboard'],
                    'body' => 'Content'
                ]
            ]
        );

        $this->assertStringContainsString('<h3>Dashboard</h3>', $html);
        $this->assertStringContainsString('Content', $html);

        // Vérifier que les deux composants sont cachés
        $fragmentCache = $vision->getFragmentCache();
        $stats = $fragmentCache->getStats();
        $this->assertEquals(2, $stats['total'], 'Should cache both Card and CardHeader');
    }

    public function testFragmentCacheInvalidationViaVision(): void
    {
        file_put_contents(
            $this->tempDir . '/components/Button.html.vis',
            '<button>{{ label }}</button>'
        );

        $vision = new Vision($this->tempDir);
        $vision->setFragmentCacheConfig(true, $this->cacheDir, 3600);

        // Rendre composant
        $html1 = $vision->renderString(
            '{{ component("Button", props) }}',
            ['props' => ['label' => 'First']]
        );

        $this->assertStringContainsString('First', $html1);

        // Invalider le cache du composant Button
        $fragmentCache = $vision->getFragmentCache();
        $deleted = $fragmentCache->invalidateComponent('components/Button');
        $this->assertGreaterThan(0, $deleted);

        // Modifier le template
        file_put_contents(
            $this->tempDir . '/components/Button.html.vis',
            '<button class="updated">{{ label }}</button>'
        );

        // Re-rendre: devrait utiliser le nouveau template
        $html2 = $vision->renderString(
            '{{ component("Button", props) }}',
            ['props' => ['label' => 'First']]
        );

        $this->assertStringContainsString('class="updated"', $html2);
    }
}
