<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Cache\WarmupManager;
use JulienLinard\Vision\Cache\TaggedCacheManager;
use JulienLinard\Vision\Cache\FileStatsCache;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
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

    public function testTaggedCacheManager(): void
    {
        $cacheDir = sys_get_temp_dir() . '/vision_tagged_cache_test_' . uniqid();
        $taggedCache = new TaggedCacheManager($cacheDir, 3600);
        
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.vis';
        file_put_contents($templateFile, 'Hello {{ name }}!');
        
        try {
            $parser = new TemplateParser();
            $compiler = new TemplateCompiler();
            
            // Parser et compiler le template
            $content = file_get_contents($templateFile);
            $parsed = $parser->parse($content);
            $compiled = $compiler->compile($parsed);
            
            // Sauvegarder avec tags
            $success = $taggedCache->saveCompiled($templateFile, $compiled, ['user', 'public']);
            $this->assertTrue($success);
            
            // Vérifier que les tags sont enregistrés
            $tags = $taggedCache->getTags();
            $this->assertContains('user', $tags);
            $this->assertContains('public', $tags);
            
            // Vérifier les clés de cache associées
            $cacheKeys = $taggedCache->getCacheKeysByTag('user');
            $this->assertNotEmpty($cacheKeys);
            
            // Invalider par tag
            $deleted = $taggedCache->invalidateByTag('user');
            $this->assertGreaterThan(0, $deleted);
            
            // Vérifier que le tag a été supprimé
            $tagsAfter = $taggedCache->getTags();
            $this->assertNotContains('user', $tagsAfter);
        } finally {
            @unlink($templateFile);
            // Nettoyer le cache
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                if ($files !== false) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                }
                @rmdir($cacheDir);
            }
        }
    }

    public function testTaggedCacheInvalidateByTags(): void
    {
        $cacheDir = sys_get_temp_dir() . '/vision_tagged_multi_test_' . uniqid();
        $taggedCache = new TaggedCacheManager($cacheDir, 3600);
        
        // Créer des templates temporaires
        $template1 = sys_get_temp_dir() . '/test_template1_' . uniqid() . '.vis';
        $template2 = sys_get_temp_dir() . '/test_template2_' . uniqid() . '.vis';
        file_put_contents($template1, 'Template 1');
        file_put_contents($template2, 'Template 2');
        
        try {
            $parser = new TemplateParser();
            $compiler = new TemplateCompiler();
            
            // Sauvegarder avec différents tags
            $parsed1 = $parser->parse(file_get_contents($template1));
            $compiled1 = $compiler->compile($parsed1);
            $taggedCache->saveCompiled($template1, $compiled1, ['tag1', 'common']);
            
            $parsed2 = $parser->parse(file_get_contents($template2));
            $compiled2 = $compiler->compile($parsed2);
            $taggedCache->saveCompiled($template2, $compiled2, ['tag2', 'common']);
            
            // Invalider plusieurs tags
            $deleted = $taggedCache->invalidateByTags(['tag1', 'tag2']);
            $this->assertGreaterThanOrEqual(2, $deleted);
            
            // Vérifier que les tags ont été supprimés
            $tags = $taggedCache->getTags();
            $this->assertNotContains('tag1', $tags);
            $this->assertNotContains('tag2', $tags);
        } finally {
            @unlink($template1);
            @unlink($template2);
            // Nettoyer le cache
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                if ($files !== false) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                }
                @rmdir($cacheDir);
            }
        }
    }

    public function testTaggedCacheCleanIndex(): void
    {
        $cacheDir = sys_get_temp_dir() . '/vision_tagged_clean_test_' . uniqid();
        $taggedCache = new TaggedCacheManager($cacheDir, 3600);
        
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.vis';
        file_put_contents($templateFile, 'Hello');
        
        try {
            $parser = new TemplateParser();
            $compiler = new TemplateCompiler();
            
            // Sauvegarder avec tag
            $parsed = $parser->parse(file_get_contents($templateFile));
            $compiled = $compiler->compile($parsed);
            $taggedCache->saveCompiled($templateFile, $compiled, ['test']);
            
            // Vérifier que le tag est enregistré
            $tags = $taggedCache->getTags();
            $this->assertContains('test', $tags);
            
            // Nettoyer l'index (devrait supprimer les références aux fichiers inexistants)
            $cleaned = $taggedCache->cleanTagIndex();
            // Le fichier existe encore, donc cleaned devrait être 0 ou plus
            $this->assertGreaterThanOrEqual(0, $cleaned);
        } finally {
            @unlink($templateFile);
            // Nettoyer le cache
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                if ($files !== false) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                }
                @rmdir($cacheDir);
            }
        }
    }

    public function testFileStatsCache(): void
    {
        $cache = new FileStatsCache(5); // TTL de 5 secondes
        
        // Créer un fichier temporaire
        $testFile = sys_get_temp_dir() . '/test_file_stats_' . uniqid() . '.txt';
        file_put_contents($testFile, 'Test content');
        
        try {
            // Premier appel : doit récupérer depuis le système
            $stats1 = $cache->getStats($testFile);
            $this->assertNotNull($stats1);
            $this->assertTrue($stats1['exists']);
            $this->assertNotNull($stats1['mtime']);
            $this->assertNotNull($stats1['size']);
            $this->assertEquals(12, $stats1['size']); // "Test content" = 12 caractères
            
            // Deuxième appel : doit utiliser le cache
            $stats2 = $cache->getStats($testFile);
            $this->assertEquals($stats1['mtime'], $stats2['mtime']);
            $this->assertEquals($stats1['size'], $stats2['size']);
            
            // Test méthodes helper
            $this->assertTrue($cache->exists($testFile));
            $this->assertNotNull($cache->mtime($testFile));
            $this->assertEquals(12, $cache->size($testFile));
            
            // Test fichier inexistant
            $nonExistent = sys_get_temp_dir() . '/non_existent_' . uniqid() . '.txt';
            $this->assertFalse($cache->exists($nonExistent));
            $this->assertNull($cache->mtime($nonExistent));
            $this->assertNull($cache->size($nonExistent));
            
            // Test invalidation
            $cache->invalidate($testFile);
            // Après invalidation, le prochain appel devrait recharger depuis le système
            $stats3 = $cache->getStats($testFile);
            $this->assertNotNull($stats3);
            $this->assertTrue($stats3['exists']);
            
            // Test clear
            $cache->clear();
            $cacheStats = $cache->getCacheStats();
            $this->assertEquals(0, $cacheStats['size']);
            $this->assertEquals(5, $cacheStats['ttl']);
        } finally {
            @unlink($testFile);
        }
    }

    public function testFileStatsCacheWithCacheManager(): void
    {
        // Test que CacheManager utilise bien FileStatsCache
        $cacheDir = sys_get_temp_dir() . '/vision_file_stats_test_' . uniqid();
        $cacheManager = new \JulienLinard\Vision\Cache\CacheManager($cacheDir, 3600);
        
        // Créer un template temporaire
        $templateFile = sys_get_temp_dir() . '/test_template_' . uniqid() . '.vis';
        file_put_contents($templateFile, 'Hello {{ name }}!');
        
        try {
            $parser = new TemplateParser();
            $compiler = new TemplateCompiler();
            
            // Parser et compiler
            $parsed = $parser->parse(file_get_contents($templateFile));
            $compiled = $compiler->compile($parsed);
            
            // Sauvegarder dans le cache
            $cacheManager->saveCompiled($templateFile, $compiled);
            
            // Récupérer depuis le cache (devrait utiliser FileStatsCache)
            $cached = $cacheManager->getCompiled($templateFile);
            $this->assertNotNull($cached);
            $this->assertEquals($compiled->phpCode, $cached->phpCode);
            
            // Vérifier que le cache fonctionne (deuxième appel devrait utiliser le cache)
            $cached2 = $cacheManager->getCompiled($templateFile);
            $this->assertNotNull($cached2);
            $this->assertEquals($compiled->phpCode, $cached2->phpCode);
        } finally {
            @unlink($templateFile);
            // Nettoyer le cache
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*');
                if ($files !== false) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                    }
                }
                @rmdir($cacheDir);
            }
        }
    }
}
