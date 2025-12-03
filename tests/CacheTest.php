<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Cache\WarmupManager;
use JulienLinard\Vision\Exception\VisionException;

class CacheTest extends TestCase
{
    private string $cacheDir;
    private Vision $vision;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/vision_cache_test_' . uniqid();
        $this->vision = new Vision();
        $this->vision->setCache(true, $this->cacheDir, 3600);
    }

    protected function tearDown(): void
    {
        // Nettoyer le cache de test
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($this->cacheDir);
        }
    }

    public function testCacheIsCreated(): void
    {
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.php';
        file_put_contents($templateFile, 'Hello {{ name }}!');

        try {
            $vision = new Vision(sys_get_temp_dir());
            $vision->setCache(true, $this->cacheDir);

            // Premier rendu (crée le cache)
            $vision->render(basename($templateFile, '.php'), ['name' => 'World']);

            // Vérifier que le cache existe
            $files = glob($this->cacheDir . '/*.cache');
            $this->assertNotEmpty($files, 'Le fichier de cache devrait être créé');
        } finally {
            @unlink($templateFile);
        }
    }

    public function testCacheIsUsed(): void
    {
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.php';
        file_put_contents($templateFile, 'Hello {{ name }}!');

        try {
            $vision = new Vision(sys_get_temp_dir());
            $vision->setCache(true, $this->cacheDir);

            // Premier rendu
            $result1 = $vision->render(basename($templateFile, '.php'), ['name' => 'World']);

            // Deuxième rendu (devrait utiliser le cache)
            $result2 = $vision->render(basename($templateFile, '.php'), ['name' => 'World']);

            $this->assertEquals($result1, $result2);
        } finally {
            @unlink($templateFile);
        }
    }

    public function testCacheInvalidationOnTemplateChange(): void
    {
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.php';
        file_put_contents($templateFile, 'Hello {{ name }}!');

        try {
            $vision = new Vision(sys_get_temp_dir());
            $vision->setCache(true, $this->cacheDir);

            // Premier rendu
            $result1 = $vision->render(basename($templateFile, '.php'), ['name' => 'World']);

            // Modifier le template
            sleep(1); // S'assurer que le timestamp change
            file_put_contents($templateFile, 'Bonjour {{ name }}!');

            // Deuxième rendu (devrait régénérer le cache)
            $result2 = $vision->render(basename($templateFile, '.php'), ['name' => 'World']);

            $this->assertNotEquals($result1, $result2);
            $this->assertStringContainsString('Bonjour', $result2);
        } finally {
            @unlink($templateFile);
        }
    }

    public function testCacheWithDifferentVariables(): void
    {
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.php';
        file_put_contents($templateFile, 'Hello {{ name }}!');

        try {
            $vision = new Vision(sys_get_temp_dir());
            $vision->setCache(true, $this->cacheDir);

            // Rendu avec variables différentes
            $result1 = $vision->render(basename($templateFile, '.php'), ['name' => 'World']);
            $result2 = $vision->render(basename($templateFile, '.php'), ['name' => 'PHP']);

            $this->assertNotEquals($result1, $result2);
            $this->assertStringContainsString('World', $result1);
            $this->assertStringContainsString('PHP', $result2);
        } finally {
            @unlink($templateFile);
        }
    }

    public function testClearCache(): void
    {
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.php';
        file_put_contents($templateFile, 'Hello {{ name }}!');

        try {
            $vision = new Vision(sys_get_temp_dir());
            $vision->setCache(true, $this->cacheDir);

            // Créer du cache
            $vision->render(basename($templateFile, '.php'), ['name' => 'World']);

            // Vérifier que le cache existe
            $filesBefore = glob($this->cacheDir . '/*.cache');
            $this->assertNotEmpty($filesBefore);

            // Nettoyer le cache
            $deleted = $vision->clearCache(0); // 0 = tout supprimer

            $this->assertGreaterThan(0, $deleted);

            // Vérifier que le cache a été supprimé
            $filesAfter = glob($this->cacheDir . '/*.cache');
            $this->assertEmpty($filesAfter);
        } finally {
            @unlink($templateFile);
        }
    }

    public function testCacheExpiration(): void
    {
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.php';
        file_put_contents($templateFile, 'Hello {{ name }}!');

        try {
            // Créer un cache avec TTL très court
            $vision = new Vision(sys_get_temp_dir());
            $vision->setCache(true, $this->cacheDir, 1); // 1 seconde

            // Premier rendu
            $vision->render(basename($templateFile, '.php'), ['name' => 'World']);

            // Attendre que le cache expire
            sleep(2);

            // Le cache devrait être considéré comme expiré lors du prochain rendu
            $result = $vision->render(basename($templateFile, '.php'), ['name' => 'World']);
            $this->assertStringContainsString('Hello', $result);
        } finally {
            @unlink($templateFile);
        }
    }

    public function testCacheDisabled(): void
    {
        $vision = new Vision();
        $vision->setCache(false);

        $template = 'Hello {{ name }}!';
        $variables = ['name' => 'World'];

        // Rendu sans cache
        $result = $vision->renderString($template, $variables);

        // Vérifier qu'aucun fichier de cache n'a été créé
        $files = glob($this->cacheDir . '/*.cache');
        $this->assertEmpty($files);
    }

    public function testCacheDirectoryCreation(): void
    {
        $newCacheDir = sys_get_temp_dir() . '/vision_new_cache_' . uniqid();

        try {
            $vision = new Vision();
            $vision->setCache(true, $newCacheDir);

            // Le répertoire devrait être créé automatiquement
            $this->assertDirectoryExists($newCacheDir);
        } finally {
            if (is_dir($newCacheDir)) {
                @rmdir($newCacheDir);
            }
        }
    }

    public function testWarmupManager(): void
    {
        $warmupManager = new WarmupManager();
        
        // Créer des templates temporaires
        $templateDir = sys_get_temp_dir() . '/vision_warmup_test_' . uniqid();
        mkdir($templateDir, 0755, true);
        
        $template1 = $templateDir . '/template1.vis';
        $template2 = $templateDir . '/template2.vis';
        file_put_contents($template1, 'Hello {{ name }}!');
        file_put_contents($template2, 'Welcome {{ user }}!');
        
        try {
            $vision = new Vision($templateDir);
            $vision->setCache(true, $this->cacheDir, 3600);
            
            // Warming avec liste de templates
            $stats = $warmupManager->warmup(
                $vision,
                ['template1.vis', 'template2.vis'],
                ['name' => 'Test', 'user' => 'User']
            );
            
            $this->assertEquals(2, $stats['warmed']);
            $this->assertEquals(0, $stats['errors']);
            $this->assertArrayHasKey('template1.vis', $stats['details']);
            $this->assertArrayHasKey('template2.vis', $stats['details']);
            $this->assertEquals('success', $stats['details']['template1.vis']);
            
            // Vérifier que le cache a été créé
            $files = glob($this->cacheDir . '/*.cache');
            $this->assertNotEmpty($files);
        } finally {
            @unlink($template1);
            @unlink($template2);
            @rmdir($templateDir);
        }
    }

    public function testWarmupFromManifest(): void
    {
        $warmupManager = new WarmupManager();
        
        // Créer un template temporaire
        $templateDir = sys_get_temp_dir() . '/vision_manifest_test_' . uniqid();
        mkdir($templateDir, 0755, true);
        
        $templateFile = $templateDir . '/test.vis';
        file_put_contents($templateFile, 'Hello {{ name }}!');
        
        // Créer un manifest JSON
        $manifestFile = $templateDir . '/manifest.json';
        $manifest = [
            'templates' => ['test.vis'],
            'variables' => ['name' => 'World']
        ];
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
        
        try {
            $vision = new Vision($templateDir);
            $vision->setCache(true, $this->cacheDir, 3600);
            
            // Warming depuis manifest
            $stats = $warmupManager->warmupFromManifest($manifestFile, $vision);
            
            $this->assertEquals(1, $stats['warmed']);
            $this->assertEquals(0, $stats['errors']);
            $this->assertEquals('success', $stats['details']['test.vis']);
        } finally {
            @unlink($templateFile);
            @unlink($manifestFile);
            @rmdir($templateDir);
        }
    }

    public function testWarmupDirectory(): void
    {
        $warmupManager = new WarmupManager();
        
        // Créer un répertoire avec templates
        $templateDir = sys_get_temp_dir() . '/vision_dir_test_' . uniqid();
        mkdir($templateDir, 0755, true);
        
        $template1 = $templateDir . '/template1.vis';
        $template2 = $templateDir . '/template2.html';
        file_put_contents($template1, 'Hello {{ name }}!');
        file_put_contents($template2, 'Welcome {{ user }}!');
        
        try {
            $vision = new Vision($templateDir);
            $vision->setCache(true, $this->cacheDir, 3600);
            
            // Warming du répertoire
            $stats = $warmupManager->warmupDirectory(
                $vision,
                $templateDir,
                ['vis', 'html'],
                ['name' => 'Test', 'user' => 'User'],
                false // non récursif
            );
            
            // Vérifier que les templates ont été réchauffés
            $this->assertGreaterThanOrEqual(2, $stats['warmed'], 'Au moins 2 templates devraient être réchauffés');
            $this->assertEquals(0, $stats['errors'], 'Aucune erreur ne devrait survenir');
            
            // Vérifier que le cache a été créé
            $files = glob($this->cacheDir . '/*.cache');
            $this->assertNotEmpty($files, 'Le cache devrait être créé');
        } finally {
            @unlink($template1);
            @unlink($template2);
            @rmdir($templateDir);
        }
    }

    public function testWarmupWithError(): void
    {
        $warmupManager = new WarmupManager();
        
        $templateDir = sys_get_temp_dir() . '/vision_error_test_' . uniqid();
        mkdir($templateDir, 0755, true);
        
        try {
            $vision = new Vision($templateDir);
            $vision->setCache(true, $this->cacheDir, 3600);
            
            // Warming avec template inexistant
            $stats = $warmupManager->warmup(
                $vision,
                ['nonexistent.vis'],
                []
            );
            
            $this->assertEquals(0, $stats['warmed']);
            $this->assertEquals(1, $stats['errors']);
            $this->assertArrayHasKey('nonexistent.vis', $stats['details']);
            $this->assertNotEquals('success', $stats['details']['nonexistent.vis']);
        } finally {
            @rmdir($templateDir);
        }
    }
}
