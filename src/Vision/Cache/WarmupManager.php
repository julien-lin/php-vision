<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Cache;

use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Exception\VisionException;

/**
 * Gestionnaire de warming du cache
 * 
 * Permet de précharger les templates dans le cache pour éviter
 * les cache misses initiaux après un déploiement.
 */
class WarmupManager
{
    /**
     * Préchauffe le cache pour une liste de templates
     * 
     * @param Vision $vision Instance Vision configurée
     * @param array<string> $templates Liste des chemins de templates à précharger
     * @param array<string, mixed> $commonVariables Variables communes à utiliser pour le warming
     * @return array{warmed: int, errors: int, details: array<string, string>} Statistiques du warming
     */
    public function warmup(Vision $vision, array $templates, array $commonVariables = []): array
    {
        $stats = [
            'warmed' => 0,
            'errors' => 0,
            'details' => []
        ];

        foreach ($templates as $template) {
            try {
                // Rendre le template pour déclencher le parsing et la compilation
                // Cela va automatiquement mettre en cache le template compilé
                $vision->render($template, $commonVariables);
                $stats['warmed']++;
                $stats['details'][$template] = 'success';
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['details'][$template] = $e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Préchauffe le cache depuis un fichier manifest JSON
     * 
     * Format du manifest :
     * {
     *   "templates": ["template1.vis", "template2.vis"],
     *   "variables": {"common": "value"}
     * }
     * 
     * @param string $manifestPath Chemin vers le fichier manifest JSON
     * @param Vision $vision Instance Vision configurée
     * @return array{warmed: int, errors: int, details: array<string, string>} Statistiques du warming
     * @throws VisionException Si le manifest est invalide ou inaccessible
     */
    public function warmupFromManifest(string $manifestPath, Vision $vision): array
    {
        if (!file_exists($manifestPath)) {
            throw new VisionException("Manifest file not found: {$manifestPath}");
        }

        $content = @file_get_contents($manifestPath);
        if ($content === false) {
            throw new VisionException("Cannot read manifest file: {$manifestPath}");
        }

        $manifest = @json_decode($content, true);
        if ($manifest === null || !is_array($manifest)) {
            throw new VisionException("Invalid manifest file format: {$manifestPath}");
        }

        if (!isset($manifest['templates']) || !is_array($manifest['templates'])) {
            throw new VisionException("Manifest must contain 'templates' array: {$manifestPath}");
        }

        $variables = $manifest['variables'] ?? [];

        return $this->warmup($vision, $manifest['templates'], $variables);
    }

    /**
     * Préchauffe le cache pour tous les templates d'un répertoire
     * 
     * @param Vision $vision Instance Vision configurée
     * @param string $directory Répertoire contenant les templates (doit correspondre au templateDir de Vision)
     * @param array<string> $extensions Extensions de fichiers à inclure (par défaut: ['vis', 'html', 'php'])
     * @param array<string, mixed> $commonVariables Variables communes à utiliser
     * @param bool $recursive Parcourir récursivement les sous-répertoires
     * @return array{warmed: int, errors: int, details: array<string, string>} Statistiques du warming
     * @throws VisionException Si le répertoire est inaccessible
     */
    public function warmupDirectory(
        Vision $vision,
        string $directory,
        array $extensions = ['vis', 'html', 'php'],
        array $commonVariables = [],
        bool $recursive = true
    ): array {
        if (!is_dir($directory)) {
            throw new VisionException("Directory not found: {$directory}");
        }

        $realDir = realpath($directory);
        if ($realDir === false) {
            throw new VisionException("Cannot resolve directory path: {$directory}");
        }

        $templates = $this->findTemplates($realDir, $extensions, $recursive);
        
        return $this->warmup($vision, $templates, $commonVariables);
    }

    /**
     * Trouve tous les templates dans un répertoire
     * Retourne les noms de templates relatifs au répertoire de base
     * 
     * @param string $directory Répertoire à parcourir (chemin réel)
     * @param array<string> $extensions Extensions à inclure
     * @param bool $recursive Parcourir récursivement
     * @return array<string> Liste des noms de templates (relatifs au répertoire)
     */
    private function findTemplates(string $directory, array $extensions, bool $recursive): array
    {
        $templates = [];
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            )
            : new \DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (in_array($extension, $extensions, true)) {
                $fullPath = $file->getRealPath();
                if ($fullPath === false) {
                    continue;
                }
                
                // Obtenir le chemin relatif au répertoire de base
                $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $fullPath);
                // Normaliser les séparateurs pour compatibilité cross-platform
                $relativePath = str_replace('\\', '/', $relativePath);
                
                $templates[] = $relativePath;
            }
        }

        return $templates;
    }
}
