<?php

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Cache\CacheManager;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;

class CacheManagerTest extends TestCase
{
    private string $cacheDir;
    private string $templateDir;
    private CacheManager $cacheManager;
    private TemplateParser $parser;
    private TemplateCompiler $compiler;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/vision_cache_test_' . uniqid();
        $this->templateDir = sys_get_temp_dir() . '/vision_templates_test_' . uniqid();
        
        mkdir($this->cacheDir, 0755, true);
        mkdir($this->templateDir, 0755, true);
        
        $this->cacheManager = new CacheManager($this->cacheDir, 3600);
        $this->parser = new TemplateParser();
        $this->compiler = new TemplateCompiler();
    }

    protected function tearDown(): void
    {
        // Nettoyer les répertoires de test
        $this->deleteDirectory($this->cacheDir);
        $this->deleteDirectory($this->templateDir);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTemplate(string $name, string $content): string
    {
        $path = $this->templateDir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $content);
        return $path;
    }

    public function testSaveAndGetParsed(): void
    {
        $templatePath = $this->createTemplate('test.tpl', 'Hello {{ name }}');
        $content = file_get_contents($templatePath);
        
        $parsed = $this->parser->parse($content);
        
        // Sauvegarder dans le cache
        $result = $this->cacheManager->saveParsed($templatePath, $parsed);
        $this->assertTrue($result);
        
        // Récupérer depuis le cache
        $cached = $this->cacheManager->getParsed($templatePath);
        $this->assertNotNull($cached);
        $this->assertEquals($parsed->content, $cached->content);
    }

    public function testSaveAndGetCompiled(): void
    {
        $templatePath = $this->createTemplate('test.tpl', 'Hello {{ name }}');
        $content = file_get_contents($templatePath);
        
        $parsed = $this->parser->parse($content);
        $compiled = $this->compiler->compile($parsed);
        
        // Sauvegarder dans le cache
        $result = $this->cacheManager->saveCompiled($templatePath, $compiled);
        $this->assertTrue($result);
        
        // Récupérer depuis le cache
        $cached = $this->cacheManager->getCompiled($templatePath);
        $this->assertNotNull($cached);
        $this->assertEquals($compiled->phpCode, $cached->phpCode);
    }

    public function testCacheInvalidationOnTemplateChange(): void
    {
        $templatePath = $this->createTemplate('test.tpl', 'Version 1');
        $content = file_get_contents($templatePath);
        
        $parsed = $this->parser->parse($content);
        $this->cacheManager->saveParsed($templatePath, $parsed);
        
        // Cache valide
        $cached = $this->cacheManager->getParsed($templatePath);
        $this->assertNotNull($cached);
        
        // Modifier le template
        sleep(1); // Assurer une différence de timestamp
        file_put_contents($templatePath, 'Version 2');
        touch($templatePath, time());
        
        // Cache doit être invalidé
        $cached = $this->cacheManager->getParsed($templatePath);
        $this->assertNull($cached);
    }

    public function testCacheTTL(): void
    {
        // Créer un cache manager avec TTL court
        $shortTTLCache = new CacheManager($this->cacheDir, 1);
        
        $templatePath = $this->createTemplate('test.tpl', 'Test');
        $content = file_get_contents($templatePath);
        
        $parsed = $this->parser->parse($content);
        $shortTTLCache->saveParsed($templatePath, $parsed);
        
        // Cache valide immédiatement
        $cached = $shortTTLCache->getParsed($templatePath);
        $this->assertNotNull($cached);
        
        // Attendre expiration du TTL
        sleep(2);
        
        // Cache doit être expiré
        $cached = $shortTTLCache->getParsed($templatePath);
        $this->assertNull($cached);
    }

    public function testClearAll(): void
    {
        $template1 = $this->createTemplate('test1.tpl', 'Test 1');
        $template2 = $this->createTemplate('test2.tpl', 'Test 2');
        
        $parsed1 = $this->parser->parse(file_get_contents($template1));
        $parsed2 = $this->parser->parse(file_get_contents($template2));
        
        $this->cacheManager->saveParsed($template1, $parsed1);
        $this->cacheManager->saveCompiled($template2, $this->compiler->compile($parsed2));
        
        // Vérifier que les caches existent
        $this->assertNotNull($this->cacheManager->getParsed($template1));
        $this->assertNotNull($this->cacheManager->getCompiled($template2));
        
        // Vider tout le cache
        $deleted = $this->cacheManager->clear(0);
        $this->assertGreaterThanOrEqual(2, $deleted);
        
        // Vérifier que les caches sont vides
        $this->assertNull($this->cacheManager->getParsed($template1));
        $this->assertNull($this->cacheManager->getCompiled($template2));
    }

    public function testClearByAge(): void
    {
        $template1 = $this->createTemplate('old.tpl', 'Old');
        $template2 = $this->createTemplate('new.tpl', 'New');
        
        $parsed1 = $this->parser->parse(file_get_contents($template1));
        $this->cacheManager->saveParsed($template1, $parsed1);
        
        sleep(2);
        
        $parsed2 = $this->parser->parse(file_get_contents($template2));
        $this->cacheManager->saveParsed($template2, $parsed2);
        
        // Supprimer les fichiers plus vieux que 1 seconde
        $deleted = $this->cacheManager->clear(1);
        $this->assertGreaterThanOrEqual(1, $deleted);
        
        // Le vieux cache doit être supprimé
        $this->assertNull($this->cacheManager->getParsed($template1));
        
        // Le nouveau cache doit exister encore
        $this->assertNotNull($this->cacheManager->getParsed($template2));
    }

    public function testGetStats(): void
    {
        $stats = $this->cacheManager->getStats();
        $this->assertEquals(0, $stats['count']);
        $this->assertEquals(0, $stats['size']);
        $this->assertNull($stats['oldest']);
        $this->assertNull($stats['newest']);
        
        // Ajouter des fichiers au cache
        $template1 = $this->createTemplate('test1.tpl', 'Test 1');
        $template2 = $this->createTemplate('test2.tpl', 'Test 2');
        
        $parsed1 = $this->parser->parse(file_get_contents($template1));
        $parsed2 = $this->parser->parse(file_get_contents($template2));
        
        $this->cacheManager->saveParsed($template1, $parsed1);
        $this->cacheManager->saveParsed($template2, $parsed2);
        
        $stats = $this->cacheManager->getStats();
        $this->assertEquals(2, $stats['count']);
        $this->assertGreaterThan(0, $stats['size']);
        $this->assertNotNull($stats['oldest']);
        $this->assertNotNull($stats['newest']);
        $this->assertLessThanOrEqual($stats['newest'], time());
    }

    public function testConcurrentWrites(): void
    {
        $templatePath = $this->createTemplate('concurrent.tpl', 'Test');
        $content = file_get_contents($templatePath);
        $parsed = $this->parser->parse($content);
        
        // Simuler des écritures concurrentes
        $success = 0;
        for ($i = 0; $i < 5; $i++) {
            if ($this->cacheManager->saveParsed($templatePath, $parsed)) {
                $success++;
            }
        }
        
        // Toutes les écritures doivent réussir
        $this->assertEquals(5, $success);
        
        // Le cache doit être lisible
        $cached = $this->cacheManager->getParsed($templatePath);
        $this->assertNotNull($cached);
    }

    public function testInvalidCacheData(): void
    {
        $templatePath = $this->createTemplate('test.tpl', 'Test');
        
        // Créer un fichier de cache avec des données invalides
        $cacheKey = 'v1_parsed_' . md5(realpath($templatePath));
        $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.cache';
        file_put_contents($cacheFile, 'invalid serialized data');
        
        // Doit retourner null pour des données invalides
        $cached = $this->cacheManager->getParsed($templatePath);
        $this->assertNull($cached);
    }
}
