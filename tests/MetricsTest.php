<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Runtime\MetricsCollector;
use JulienLinard\Vision\Runtime\VisionLogger;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;

class MetricsTest extends TestCase
{
    private Vision $vision;
    private string $cacheDir;
    private string $templateDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/vision_metrics_cache_' . uniqid();
        $this->templateDir = sys_get_temp_dir() . '/vision_metrics_templates_' . uniqid();
        mkdir($this->cacheDir, 0755, true);
        mkdir($this->templateDir, 0755, true);
        
        $this->vision = new Vision($this->templateDir);
    }

    protected function tearDown(): void
    {
        // Nettoyer
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

    public function testMetricsCollector(): void
    {
        $collector = new MetricsCollector();
        
        // Enregistrer quelques rendus
        $collector->recordRender(0.001, true); // cache hit
        $collector->recordRender(0.002, false); // cache miss
        $collector->recordRender(0.0015, true); // cache hit
        
        $metrics = $collector->getMetrics();
        
        $this->assertEquals(3, $metrics['render_count']);
        $this->assertEquals(2, $metrics['cache_hits']);
        $this->assertEquals(1, $metrics['cache_misses']);
        $this->assertGreaterThan(0, $metrics['render_time_avg']);
        
        // Vérifier le taux de cache hit
        $hitRate = $collector->getCacheHitRate();
        $this->assertEquals(66.67, round($hitRate, 2));
    }

    public function testMetricsCollectorCompilation(): void
    {
        $collector = new MetricsCollector();
        
        $collector->recordCompilation(0.01);
        $collector->recordCompilation(0.02);
        
        $metrics = $collector->getMetrics();
        
        $this->assertEquals(2, $metrics['compilation_count']);
        $this->assertEquals(0.015, $metrics['compilation_time_avg']);
    }

    public function testMetricsCollectorSummary(): void
    {
        $collector = new MetricsCollector();
        
        $collector->recordRender(0.001, true);
        $collector->recordRender(0.002, false);
        
        $summary = $collector->getSummary();
        
        $this->assertArrayHasKey('renders', $summary);
        $this->assertArrayHasKey('cache', $summary);
        $this->assertArrayHasKey('compilation', $summary);
        $this->assertEquals(2, $summary['renders']['count']);
        $this->assertGreaterThan(0, $summary['renders']['avg_time_ms']);
    }

    public function testMetricsCollectorReset(): void
    {
        $collector = new MetricsCollector();
        
        $collector->recordRender(0.001, true);
        $collector->reset();
        
        $metrics = $collector->getMetrics();
        $this->assertEquals(0, $metrics['render_count']);
    }

    public function testVisionWithMetrics(): void
    {
        $collector = new MetricsCollector();
        $this->vision->setMetricsCollector($collector);
        
        // Rendre un template
        $template = 'Hello {{ name }}!';
        $this->vision->renderString($template, ['name' => 'World']);
        
        $metrics = $collector->getMetrics();
        $this->assertEquals(1, $metrics['render_count']);
        $this->assertGreaterThan(0, $metrics['render_time_total']);
    }

    public function testVisionWithMetricsAndCache(): void
    {
        $collector = new MetricsCollector();
        $this->vision->setMetricsCollector($collector);
        $this->vision->setCache(true, $this->cacheDir, 3600);
        
        // Activer le pipeline compilé
        $parser = new TemplateParser();
        $compiler = new TemplateCompiler();
        $cacheManager = new CacheManager($this->cacheDir, 86400);
        $this->vision->setParser($parser);
        $this->vision->setCompiler($compiler);
        $this->vision->setCacheManager($cacheManager);
        
        // Créer un template
        $templateFile = $this->templateDir . '/test.vis';
        file_put_contents($templateFile, 'Hello {{ name }}!');
        
        try {
            // Premier rendu (cache miss)
            $this->vision->render('test', ['name' => 'World']);
            
            // Attendre un peu pour que le cache soit bien écrit
            usleep(100000); // 100ms
            
            // Deuxième rendu (cache hit)
            $this->vision->render('test', ['name' => 'World']);
            
            $metrics = $collector->getMetrics();
            $this->assertEquals(2, $metrics['render_count']);
            $this->assertEquals(1, $metrics['cache_hits']);
            $this->assertEquals(1, $metrics['cache_misses']);
            $this->assertGreaterThan(0, $metrics['compilation_count']);
        } finally {
            @unlink($templateFile);
        }
    }

    public function testVisionLogger(): void
    {
        $logger = new VisionLogger('debug', true);
        
        // Tester les différents niveaux
        $logger->debug('Debug message', ['key' => 'value']);
        $logger->info('Info message', ['key' => 'value']);
        $logger->warning('Warning message', ['key' => 'value']);
        $logger->error('Error message', ['key' => 'value']);
        
        // Vérifier que le logger fonctionne (pas d'exception)
        $this->assertTrue($logger->isEnabled());
    }

    public function testVisionLoggerLevels(): void
    {
        // Logger avec niveau minimum 'warning'
        $logger = new VisionLogger('warning', true);
        
        // Les messages debug et info ne devraient pas être loggés
        // (on ne peut pas vraiment tester sans mock, mais on vérifie qu'il n'y a pas d'erreur)
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');
        
        $this->assertTrue(true); // Si on arrive ici, pas d'erreur
    }

    public function testVisionLoggerDisabled(): void
    {
        $logger = new VisionLogger('info', false);
        
        // Logger désactivé ne devrait rien faire
        $logger->info('Message');
        $logger->error('Error');
        
        $this->assertFalse($logger->isEnabled());
    }

    public function testVisionWithLogger(): void
    {
        $logger = new VisionLogger('info', true);
        $this->vision->setLogger($logger);
        
        // Rendre un template (devrait logger)
        $template = 'Hello {{ name }}!';
        $result = $this->vision->renderString($template, ['name' => 'World']);
        
        $this->assertEquals('Hello World!', $result);
        // Si on arrive ici, le logger n'a pas causé d'erreur
    }
}
