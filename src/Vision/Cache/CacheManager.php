<?php

namespace JulienLinard\Vision\Cache;

use JulienLinard\Vision\Exception\VisionException;
use JulienLinard\Vision\Parser\ParsedTemplate;
use JulienLinard\Vision\Compiler\CompiledTemplate;

/**
 * Gestionnaire de cache pour les templates Vision
 * 
 * Responsabilité: Gérer le stockage, la récupération et la validation du cache
 * Supporte deux types de cache:
 * - Cache de templates parsés (Parser)
 * - Cache de templates compilés (Compiler)
 */
class CacheManager
{
    private const CACHE_VERSION = 'v1';
    private const LOCK_TIMEOUT = 5; // secondes
    private const LOCK_RETRY_INTERVAL = 50000; // microsecondes (50ms)

    private ?FileStatsCache $fileStatsCache = null;

    public function __construct(
        private readonly string $cacheDir,
        private readonly int $ttl = 3600
    ) {
        // Créer le répertoire de cache s'il n'existe pas
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
                throw new VisionException("Impossible de créer le répertoire de cache: {$this->cacheDir}");
            }
        }
    }

    /**
     * Obtient l'instance du cache de statistiques de fichiers
     */
    private function getFileStatsCache(): FileStatsCache
    {
        if ($this->fileStatsCache === null) {
            $this->fileStatsCache = new FileStatsCache(5); // TTL de 5 secondes
        }
        return $this->fileStatsCache;
    }

    /**
     * Récupère un template parsé depuis le cache
     * 
     * @param string $templatePath Chemin du template source
     * @return ParsedTemplate|null Template parsé ou null si pas en cache/expiré
     */
    public function getParsed(string $templatePath): ?ParsedTemplate
    {
        $cacheKey = $this->generateCacheKey($templatePath, 'parsed');
        $cacheFile = $this->getCacheFilePath($cacheKey);

        if (!$this->isCacheValid($cacheFile, $templatePath)) {
            return null;
        }

        $cached = $this->readCache($cacheFile);
        if ($cached === null) {
            return null;
        }

        // Vérifier que c'est bien un ParsedTemplate
        if (!($cached instanceof ParsedTemplate)) {
            return null;
        }

        return $cached;
    }

    /**
     * Sauvegarde un template parsé dans le cache
     * 
     * @param string $templatePath Chemin du template source
     * @param ParsedTemplate $parsed Template parsé
     * @return bool Succès de la sauvegarde
     */
    public function saveParsed(string $templatePath, ParsedTemplate $parsed): bool
    {
        $cacheKey = $this->generateCacheKey($templatePath, 'parsed');
        $cacheFile = $this->getCacheFilePath($cacheKey);

        return $this->writeCache($cacheFile, $parsed);
    }

    /**
     * Récupère un template compilé depuis le cache
     * 
     * @param string $templatePath Chemin du template source
     * @return CompiledTemplate|null Template compilé ou null si pas en cache/expiré
     */
    public function getCompiled(string $templatePath): ?CompiledTemplate
    {
        $cacheKey = $this->generateCacheKey($templatePath, 'compiled');
        $cacheFile = $this->getCacheFilePath($cacheKey);

        if (!$this->isCacheValid($cacheFile, $templatePath)) {
            return null;
        }

        $cached = $this->readCache($cacheFile);
        if ($cached === null) {
            return null;
        }

        // Vérifier que c'est bien un CompiledTemplate
        if (!($cached instanceof CompiledTemplate)) {
            return null;
        }

        return $cached;
    }

    /**
     * Sauvegarde un template compilé dans le cache
     * 
     * @param string $templatePath Chemin du template source
     * @param CompiledTemplate $compiled Template compilé
     * @return bool Succès de la sauvegarde
     */
    public function saveCompiled(string $templatePath, CompiledTemplate $compiled): bool
    {
        $cacheKey = $this->generateCacheKey($templatePath, 'compiled');
        $cacheFile = $this->getCacheFilePath($cacheKey);

        $result = $this->writeCache($cacheFile, $compiled);
        
        // Invalider le cache de stats pour forcer la relecture au prochain appel
        if ($result) {
            $this->getFileStatsCache()->invalidate($cacheFile);
        }
        
        return $result;
    }

    /**
     * Génère une clé de cache unique
     * 
     * @param string $templatePath Chemin du template
     * @param string $type Type de cache ('parsed' ou 'compiled')
     * @return string Clé de cache
     */
    private function generateCacheKey(string $templatePath, string $type): string
    {
        // Utiliser le chemin réel résolu pour éviter les collisions
        $realPath = realpath($templatePath);
        if ($realPath === false) {
            $realPath = $templatePath;
        }

        return self::CACHE_VERSION . '_' . $type . '_' . md5($realPath);
    }

    /**
     * Obtient le chemin complet du fichier de cache
     */
    private function getCacheFilePath(string $cacheKey): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.cache';
    }

    /**
     * Vérifie si le cache est valide
     * 
     * @param string $cacheFile Fichier de cache
     * @param string $templatePath Template source
     * @return bool True si le cache est valide
     */
    private function isCacheValid(string $cacheFile, string $templatePath): bool
    {
        $statsCache = $this->getFileStatsCache();

        // Vérifier existence du fichier de cache
        if (!$statsCache->exists($cacheFile)) {
            return false;
        }

        // Vérifier le TTL
        $cacheTime = $statsCache->mtime($cacheFile);
        if ($cacheTime === null || (time() - $cacheTime) > $this->ttl) {
            return false;
        }

        // Vérifier que le template source n'a pas été modifié
        // Invalider d'abord le cache de stats pour le template pour forcer la relecture
        // (important si le template vient d'être modifié)
        $statsCache->invalidate($templatePath);
        $templateTime = $statsCache->mtime($templatePath);
        // Si le template n'existe pas, invalider le cache
        if ($templateTime === null) {
            return false;
        }
        // Si le template a été modifié après la création du cache, invalider
        // Note: On utilise > (strict) car si les timestamps sont égaux, cela signifie
        // que le template et le cache ont été créés en même temps, ce qui est valide
        if ($templateTime > $cacheTime) {
            return false;
        }

        return true;
    }

    /**
     * Lit un fichier de cache
     * 
     * @return mixed|null Contenu désérialisé ou null en cas d'erreur
     */
    private function readCache(string $cacheFile): mixed
    {
        $fp = @fopen($cacheFile, 'r');
        if ($fp === false) {
            return null;
        }

        try {
            // Verrouillage partagé pour lecture
            if (!flock($fp, LOCK_SH)) {
                return null;
            }

            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);

            if ($content === false) {
                return null;
            }

            // Désérialiser avec validation
            $data = @unserialize($content);
            
            // Validation de sécurité : vérifier que c'est un objet attendu
            if (!is_object($data)) {
                return null;
            }

            $allowedClasses = [
                ParsedTemplate::class,
                CompiledTemplate::class,
                \JulienLinard\Vision\Parser\ASTNode::class,
                \JulienLinard\Vision\Parser\Token::class,
                \JulienLinard\Vision\Parser\TokenType::class,
                \JulienLinard\Vision\Parser\NodeType::class,
            ];

            $className = get_class($data);
            if (!in_array($className, $allowedClasses, true)) {
                return null;
            }

            return $data;
        } finally {
            fclose($fp);
        }
    }

    /**
     * Écrit dans un fichier de cache avec verrouillage
     * 
     * @param string $cacheFile Fichier de cache
     * @param mixed $data Données à sauvegarder
     * @return bool Succès de l'écriture
     */
    private function writeCache(string $cacheFile, mixed $data): bool
    {
        $fp = @fopen($cacheFile, 'c');
        if ($fp === false) {
            return false;
        }

        try {
            // Verrouillage exclusif avec timeout
            $startTime = time();
            $locked = false;

            while (!$locked && (time() - $startTime) < self::LOCK_TIMEOUT) {
                $locked = flock($fp, LOCK_EX | LOCK_NB);
                if (!$locked) {
                    usleep(self::LOCK_RETRY_INTERVAL);
                }
            }

            if (!$locked) {
                return false;
            }

            // Tronquer le fichier
            ftruncate($fp, 0);
            rewind($fp);

            // Sérialiser et écrire
            $serialized = serialize($data);
            $written = fwrite($fp, $serialized);

            flock($fp, LOCK_UN);

            return $written !== false && $written === strlen($serialized);
        } finally {
            fclose($fp);
        }
    }

    /**
     * Vide le cache
     * 
     * @param int $maxAge Âge maximum en secondes (0 = tout vider)
     * @return int Nombre de fichiers supprimés
     */
    public function clear(int $maxAge = 0): int
    {
        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $deleted = 0;
        $now = time();
        
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');
        if ($files === false) {
            return 0;
        }

        $statsCache = $this->getFileStatsCache();

        foreach ($files as $file) {
            if (is_file($file)) {
                $fileTime = $statsCache->mtime($file);
                if ($fileTime === null) {
                    continue;
                }

                if ($maxAge === 0 || ($now - $fileTime) > $maxAge) {
                    if (@unlink($file)) {
                        $deleted++;
                        // Invalider le cache pour ce fichier
                        $statsCache->invalidate($file);
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Obtient les statistiques du cache
     * 
     * @return array{count: int, size: int, oldest: int|null, newest: int|null}
     */
    public function getStats(): array
    {
        if (!is_dir($this->cacheDir)) {
            return ['count' => 0, 'size' => 0, 'oldest' => null, 'newest' => null];
        }

        $count = 0;
        $size = 0;
        $oldest = null;
        $newest = null;

        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');
        if ($files === false) {
            return ['count' => 0, 'size' => 0, 'oldest' => null, 'newest' => null];
        }

        $statsCache = $this->getFileStatsCache();

        foreach ($files as $file) {
            if (is_file($file)) {
                $count++;
                $fileSize = $statsCache->size($file);
                $fileTime = $statsCache->mtime($file);

                if ($fileSize !== null) {
                    $size += $fileSize;
                }

                if ($fileTime !== null) {
                    if ($oldest === null || $fileTime < $oldest) {
                        $oldest = $fileTime;
                    }
                    if ($newest === null || $fileTime > $newest) {
                        $newest = $fileTime;
                    }
                }
            }
        }

        return [
            'count' => $count,
            'size' => $size,
            'oldest' => $oldest,
            'newest' => $newest,
        ];
    }
}
