<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
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
}
