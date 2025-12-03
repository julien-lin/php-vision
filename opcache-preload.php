<?php

/**
 * OPcache Preloading Script pour PHP Vision
 * 
 * Ce script précharge toutes les classes PHP Vision dans OPcache
 * pour améliorer les performances en production.
 * 
 * Configuration requise dans php.ini :
 * opcache.preload=/path/to/php-vision/opcache-preload.php
 * opcache.preload_user=www-data
 * 
 * @see https://www.php.net/manual/en/opcache.preloading.php
 */

if (!function_exists('opcache_compile_file')) {
    // OPcache n'est pas disponible ou preloading n'est pas supporté
    return;
}

$baseDir = __DIR__ . '/src/Vision';

if (!is_dir($baseDir)) {
    return;
}

// Parcourir récursivement tous les fichiers PHP dans src/Vision
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$compiled = 0;
$errors = 0;

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filePath = $file->getRealPath();
        
        // Vérifier que le fichier existe et est lisible
        if ($filePath && is_readable($filePath)) {
            try {
                // Pré-compiler le fichier dans OPcache
                // Note: opcache_compile_file retourne false si OPcache n'est pas démarré
                // ou si le fichier ne peut pas être compilé
                $result = @opcache_compile_file($filePath);
                if ($result) {
                    $compiled++;
                } else {
                    // Ignorer silencieusement si OPcache n'est pas démarré
                    // (normal en environnement CLI ou développement)
                    $errors++;
                }
            } catch (Throwable $e) {
                // Ignorer les erreurs silencieusement en production
                // (peut être loggé si nécessaire)
                $errors++;
            }
        }
    }
}

// Note: Les statistiques peuvent être loggées si nécessaire
// error_log("OPcache Preload: {$compiled} fichiers compilés, {$errors} erreurs");
