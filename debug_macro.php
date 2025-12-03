<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;

$tempDir = sys_get_temp_dir() . '/vision_debug_' . uniqid();
mkdir($tempDir);
$cacheDir = $tempDir . '/cache';
mkdir($cacheDir);

file_put_contents(
    $tempDir . '/test.html',
    <<<'TPL'
{% macro hello(name) %}
Hello, {{ name }}!
{% endmacro %}

{{ hello('World') }}
TPL
);

$vision = new Vision($tempDir);
$parser = new TemplateParser();
$compiler = new TemplateCompiler();
$cacheManager = new CacheManager($cacheDir);

$vision->setParser($parser);
$vision->setCompiler($compiler);
$vision->setCacheManager($cacheManager);
$vision->setCache(true, $cacheDir);

try {
    $result = $vision->render('test.html');
    echo "Result:\n";
    echo $result . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";

    // Afficher le code compilé
    $cacheFiles = glob($cacheDir . '/*');
    if ($cacheFiles) {
        echo "\n=== Compiled Code ===\n";
        echo file_get_contents($cacheFiles[0]) . "\n";
    }
}

// Cleanup
array_map('unlink', glob($tempDir . '/*'));
array_map('unlink', glob($cacheDir . '/*'));
rmdir($cacheDir);
rmdir($tempDir);
