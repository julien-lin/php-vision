<?php

namespace JulienLinard\Vision\Cache;

/**
 * Cache des statistiques de fichiers pour réduire les appels système
 * 
 * Cache les résultats de file_exists(), filemtime(), et filesize()
 * pour éviter les appels système répétés sur les mêmes fichiers.
 */
class FileStatsCache
{
    private array $stats = [];
    private int $ttl;
    private const MAX_CACHE_SIZE = 1000;

    /**
     * @param int $ttl Durée de vie du cache en secondes (défaut: 5)
     */
    public function __construct(int $ttl = 5)
    {
        $this->ttl = $ttl;
    }

    /**
     * Obtient les statistiques d'un fichier (existe, mtime, size)
     * 
     * @param string $filepath Chemin du fichier
     * @return array{exists: bool, mtime: int|null, size: int|null}|null null si fichier inexistant
     */
    public function getStats(string $filepath): ?array
    {
        $key = md5($filepath);
        $now = time();

        // Cache hit : vérifier si valide
        if (isset($this->stats[$key])) {
            $cached = $this->stats[$key];
            if (($now - $cached['time']) < $this->ttl) {
                return $cached['stats'];
            }
            // Cache expiré, supprimer
            unset($this->stats[$key]);
        }

        // Vérifier existence
        if (!file_exists($filepath)) {
            // Mettre en cache l'absence (avec TTL plus court)
            if (count($this->stats) < self::MAX_CACHE_SIZE) {
                $this->stats[$key] = [
                    'stats' => ['exists' => false, 'mtime' => null, 'size' => null],
                    'time' => $now,
                ];
            }
            return null;
        }

        // Récupérer les statistiques
        $mtime = @filemtime($filepath);
        $size = @filesize($filepath);

        $stats = [
            'exists' => true,
            'mtime' => $mtime !== false ? $mtime : null,
            'size' => $size !== false ? $size : null,
        ];

        // Mettre en cache (limiter la taille pour éviter fuite mémoire)
        if (count($this->stats) < self::MAX_CACHE_SIZE) {
            $this->stats[$key] = ['stats' => $stats, 'time' => $now];
        } else {
            // Nettoyer cache ancien si limite atteinte
            $this->cleanup();
            // Réessayer si espace libéré
            if (count($this->stats) < self::MAX_CACHE_SIZE) {
                $this->stats[$key] = ['stats' => $stats, 'time' => $now];
            }
        }

        return $stats;
    }

    /**
     * Vérifie si un fichier existe (utilise le cache)
     * 
     * @param string $filepath Chemin du fichier
     * @return bool True si le fichier existe
     */
    public function exists(string $filepath): bool
    {
        $stats = $this->getStats($filepath);
        return $stats !== null && $stats['exists'];
    }

    /**
     * Obtient le temps de modification d'un fichier (utilise le cache)
     * 
     * @param string $filepath Chemin du fichier
     * @return int|null Timestamp de modification ou null si inexistant
     */
    public function mtime(string $filepath): ?int
    {
        $stats = $this->getStats($filepath);
        return $stats !== null ? $stats['mtime'] : null;
    }

    /**
     * Obtient la taille d'un fichier (utilise le cache)
     * 
     * @param string $filepath Chemin du fichier
     * @return int|null Taille en octets ou null si inexistant
     */
    public function size(string $filepath): ?int
    {
        $stats = $this->getStats($filepath);
        return $stats !== null ? $stats['size'] : null;
    }

    /**
     * Invalide le cache pour un fichier spécifique
     * 
     * @param string $filepath Chemin du fichier
     */
    public function invalidate(string $filepath): void
    {
        $key = md5($filepath);
        unset($this->stats[$key]);
    }

    /**
     * Vide tout le cache
     */
    public function clear(): void
    {
        $this->stats = [];
    }

    /**
     * Nettoie le cache des entrées expirées
     */
    private function cleanup(): void
    {
        $now = time();
        foreach ($this->stats as $key => $cached) {
            if (($now - $cached['time']) >= $this->ttl) {
                unset($this->stats[$key]);
            }
        }
    }

    /**
     * Obtient les statistiques du cache
     * 
     * @return array{size: int, ttl: int}
     */
    public function getCacheStats(): array
    {
        return [
            'size' => count($this->stats),
            'ttl' => $this->ttl,
        ];
    }
}
