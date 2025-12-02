<?php

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;

class IntegrationCompiledPipelineTest extends TestCase
{
    private string $templatesDir;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->templatesDir = sys_get_temp_dir() . '/vision_it_tpl_' . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/vision_it_cache_' . uniqid();
        @mkdir($this->templatesDir, 0755, true);
        @mkdir($this->cacheDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->templatesDir);
        $this->deleteDirectory($this->cacheDir);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function writeTemplate(string $name, string $content): string
    {
        $path = $this->templatesDir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $content);
        return $path;
    }

    public function testCompiledPipelineRendersSameAsLegacy(): void
    {
        $templateContent = <<<'TPL'
    <h1>{{ title | upper }}</h1>
    {% for item in items %}{% if item > 1 %}<p>{{ item }}</p>{% endif %}{% endfor %}
    {% if active %}OK{% else %}KO{% endif %}
    TPL;
        $this->writeTemplate('demo.php', $templateContent);

        $vars = [
            'title' => 'Hello',
            'items' => [0, 1, 2, 3],
            'active' => true,
        ];

        // Legacy pipeline (fallback)
        $legacy = new Vision($this->templatesDir, true);
        $legacy->setCache(false); // pas de cache pour référence
        $expected = $legacy->render('demo', $vars);

        // Compiled pipeline
        $vision = new Vision($this->templatesDir, true);
        $vision->setCache(true, $this->cacheDir, 3600);
        $vision->setParser(new TemplateParser())
               ->setCompiler(new TemplateCompiler())
               ->setCacheManager(new CacheManager($this->cacheDir, 3600));

        $actual = $vision->render('demo', $vars);

        $this->assertSame($expected, $actual, 'Le rendu compilé doit être identique au legacy');
    }

    public function testCompiledCacheIsReused(): void
    {
        $templateContent = '{{ title }} - {% if active %}OK{% else %}KO{% endif %}';
        $templatePath = $this->writeTemplate('cache.php', $templateContent);

        $vision = new Vision($this->templatesDir, true);
        $vision->setCache(true, $this->cacheDir, 3600);
        $parser = new TemplateParser();
        $compiler = new TemplateCompiler();
        $cacheManager = new CacheManager($this->cacheDir, 3600);
        $vision->setParser($parser)->setCompiler($compiler)->setCacheManager($cacheManager);

        $vars = ['title' => 'Test', 'active' => false];

        // 1er rendu: compile et met en cache
        $r1 = $vision->render('cache', $vars);
        $this->assertStringContainsString('Test - KO', $r1);

        // Vérifier que le cache compilé est présent
        $compiled = $cacheManager->getCompiled($templatePath);
        $this->assertNotNull($compiled, 'Le template compilé devrait être en cache');

        // 2e rendu avec mêmes variables: doit réutiliser le compilé
        $r2 = $vision->render('cache', $vars);
        $this->assertSame($r1, $r2);
    }
}
