<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Vision\Vision;

// Template avec macro simple
$template = <<<'TPL'
{% macro hello(name) %}
Hello {{ name }}!
{% endmacro %}

{{ hello('World') }}
TPL;

// Créer Vision avec debug
$vision = new Vision(__DIR__ . '/templates', __DIR__ . '/cache');

// Créer un fichier template temporaire
$testFile = __DIR__ . '/templates/debug_test.html';
@mkdir(__DIR__ . '/templates', 0777, true);
file_put_contents($testFile, $template);

// Hook pour intercepter la compilation
$compiler = $vision->getCompiler();

// Créer une version modifiée de TemplateCompiler pour debug
$reflection = new ReflectionClass($compiler);

// Obtenir le code compilé sans l'exécuter
try {
    $parser = $vision->getParser();
    $ast = $parser->parse($template, 'debug_test.html');

    echo "=== AST ===\n";
    print_r($ast);

    // Essayer de compiler
    echo "\n=== COMPILATION ===\n";
    $compiled = $compiler->compile($ast, 'debug_test.html');

    echo "\n=== COMPILED CODE ===\n";
    echo $compiled;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

// Nettoyer
@unlink($testFile);
