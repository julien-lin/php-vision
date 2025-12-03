<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Cache;

use JulienLinard\Vision\Parser\ParsedTemplate;
use JulienLinard\Vision\Compiler\CompiledTemplate;

/**
 * Gestionnaire de cache avec support des tags pour invalidation granulaire
 * 
 * Permet d'invalider le cache par tags plutôt que globalement,
 * ce qui est plus efficace en production.
 */
class TaggedCacheManager extends CacheManager
{
    /**
     * Index des tags : tag => [cacheKeys]
     * 
     * @var array<string, array<string>>
     */
    private array $tagIndex = [];

    /**
     * Fichier de persistance de l'index des tags
     */
    private string $tagIndexFile;

    public function __construct(
        string $cacheDir,
        int $ttl = 3600
    ) {
        parent::__construct($cacheDir, $ttl);
        
        $this->tagIndexFile = $cacheDir . DIRECTORY_SEPARATOR . '.tag_index';
        $this->loadTagIndex();
    }

    /**
     * Sauvegarde un template parsé dans le cache avec tags optionnels
     * 
     * @param string $templatePath Chemin du template source
     * @param ParsedTemplate $parsed Template parsé
     * @param array<string> $tags Tags associés au template
     * @return bool Succès de la sauvegarde
     */
    public function saveParsed(string $templatePath, ParsedTemplate $parsed, array $tags = []): bool
    {
        $success = parent::saveParsed($templatePath, $parsed);
        
        if ($success && !empty($tags)) {
            $cacheKey = $this->generateCacheKey($templatePath, 'parsed');
            $this->addTagsToIndex($cacheKey, $tags);
        }
        
        return $success;
    }

    /**
     * Sauvegarde un template compilé dans le cache avec tags optionnels
     * 
     * @param string $templatePath Chemin du template source
     * @param CompiledTemplate $compiled Template compilé
     * @param array<string> $tags Tags associés au template
     * @return bool Succès de la sauvegarde
     */
    public function saveCompiled(string $templatePath, CompiledTemplate $compiled, array $tags = []): bool
    {
        $success = parent::saveCompiled($templatePath, $compiled);
        
        if ($success && !empty($tags)) {
            $cacheKey = $this->generateCacheKey($templatePath, 'compiled');
            $this->addTagsToIndex($cacheKey, $tags);
        }
        
        return $success;
    }

    /**
     * Invalide tous les templates associés à un tag
     * 
     * @param string $tag Tag à invalider
     * @return int Nombre de fichiers de cache supprimés
     */
    public function invalidateByTag(string $tag): int
    {
        if (!isset($this->tagIndex[$tag])) {
            return 0;
        }

        $deleted = 0;
        $cacheKeys = $this->tagIndex[$tag];
        
        foreach ($cacheKeys as $cacheKey) {
            $cacheFile = $this->getCacheFilePath($cacheKey);
            if (file_exists($cacheFile) && @unlink($cacheFile)) {
                $deleted++;
            }
        }

        // Supprimer le tag de l'index
        unset($this->tagIndex[$tag]);
        $this->saveTagIndex();

        return $deleted;
    }

    /**
     * Invalide tous les templates associés à plusieurs tags
     * 
     * @param array<string> $tags Tags à invalider
     * @return int Nombre total de fichiers de cache supprimés
     */
    public function invalidateByTags(array $tags): int
    {
        $totalDeleted = 0;
        
        foreach ($tags as $tag) {
            $totalDeleted += $this->invalidateByTag($tag);
        }
        
        return $totalDeleted;
    }

    /**
     * Obtient tous les tags actuellement dans l'index
     * 
     * @return array<string> Liste des tags
     */
    public function getTags(): array
    {
        return array_keys($this->tagIndex);
    }

    /**
     * Obtient les clés de cache associées à un tag
     * 
     * @param string $tag Tag à rechercher
     * @return array<string> Liste des clés de cache
     */
    public function getCacheKeysByTag(string $tag): array
    {
        return $this->tagIndex[$tag] ?? [];
    }

    /**
     * Nettoie l'index des tags en supprimant les références à des fichiers inexistants
     * 
     * @return int Nombre de références nettoyées
     */
    public function cleanTagIndex(): int
    {
        $cleaned = 0;
        
        foreach ($this->tagIndex as $tag => $cacheKeys) {
            $validKeys = [];
            
            foreach ($cacheKeys as $cacheKey) {
                $cacheFile = $this->getCacheFilePath($cacheKey);
                if (file_exists($cacheFile)) {
                    $validKeys[] = $cacheKey;
                } else {
                    $cleaned++;
                }
            }
            
            if (empty($validKeys)) {
                unset($this->tagIndex[$tag]);
            } else {
                $this->tagIndex[$tag] = $validKeys;
            }
        }
        
        $this->saveTagIndex();
        
        return $cleaned;
    }

    /**
     * Ajoute des tags à l'index pour une clé de cache
     * 
     * @param string $cacheKey Clé de cache
     * @param array<string> $tags Tags à ajouter
     * @return void
     */
    private function addTagsToIndex(string $cacheKey, array $tags): void
    {
        foreach ($tags as $tag) {
            if (!isset($this->tagIndex[$tag])) {
                $this->tagIndex[$tag] = [];
            }
            
            // Éviter les doublons
            if (!in_array($cacheKey, $this->tagIndex[$tag], true)) {
                $this->tagIndex[$tag][] = $cacheKey;
            }
        }
        
        $this->saveTagIndex();
    }

    /**
     * Charge l'index des tags depuis le fichier de persistance
     * 
     * @return void
     */
    private function loadTagIndex(): void
    {
        if (!file_exists($this->tagIndexFile)) {
            $this->tagIndex = [];
            return;
        }

        $content = @file_get_contents($this->tagIndexFile);
        if ($content === false) {
            $this->tagIndex = [];
            return;
        }

        $data = @json_decode($content, true);
        if (!is_array($data)) {
            $this->tagIndex = [];
            return;
        }

        $this->tagIndex = $data;
    }

    /**
     * Sauvegarde l'index des tags dans le fichier de persistance
     * 
     * @return void
     */
    private function saveTagIndex(): void
    {
        $content = json_encode($this->tagIndex, JSON_PRETTY_PRINT);
        if ($content === false) {
            return;
        }

        @file_put_contents($this->tagIndexFile, $content, LOCK_EX);
    }

    /**
     * Obtient le chemin complet du fichier de cache
     * (Override pour accéder à la méthode privée parent)
     * 
     * @param string $cacheKey Clé de cache
     * @return string Chemin complet
     */
    private function getCacheFilePath(string $cacheKey): string
    {
        // Utiliser la réflexion pour accéder à la propriété privée du parent
        $cacheDir = $this->getCacheDir();
        return $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.cache';
    }

    /**
     * Génère une clé de cache
     * (Override pour accéder à la méthode privée parent)
     * 
     * @param string $templatePath Chemin du template
     * @param string $type Type de cache
     * @return string Clé de cache
     */
    private function generateCacheKey(string $templatePath, string $type): string
    {
        // Utiliser la réflexion pour accéder à la méthode privée du parent
        $reflection = new \ReflectionClass(CacheManager::class);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);
        return $method->invoke($this, $templatePath, $type);
    }

    /**
     * Obtient le répertoire de cache
     * 
     * @return string Chemin du répertoire de cache
     */
    private function getCacheDir(): string
    {
        $reflection = new \ReflectionClass(CacheManager::class);
        $property = $reflection->getProperty('cacheDir');
        $property->setAccessible(true);
        return $property->getValue($this);
    }
}
