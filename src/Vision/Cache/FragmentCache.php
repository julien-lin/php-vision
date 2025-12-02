<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Cache;

/**
 * Fragment Cache - Cache les composants individuellement
 * Optimise le rendu en cachant des fragments de template par clé unique
 */
class FragmentCache
{
    private string $cacheDir;
    private int $ttl;
    private bool $enabled;

    /**
     * @param string $cacheDir Répertoire pour le cache des fragments
     * @param int $ttl Durée de validité en secondes (défaut: 3600 = 1h)
     * @param bool $enabled Activer/désactiver le cache
     */
    public function __construct(string $cacheDir, int $ttl = 3600, bool $enabled = true)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        $this->ttl = $ttl;
        $this->enabled = $enabled;

        if ($enabled && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Récupère un fragment du cache
     *
     * @param string $key Clé unique du fragment
     * @return string|null Contenu du fragment ou null si absent/expiré
     */
    public function get(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        // Vérifier expiration
        $mtime = filemtime($filePath);
        if ($mtime === false || (time() - $mtime) > $this->ttl) {
            @unlink($filePath);
            return null;
        }

        $content = file_get_contents($filePath);
        return $content !== false ? $content : null;
    }

    /**
     * Stocke un fragment dans le cache
     *
     * @param string $key Clé unique du fragment
     * @param string $content Contenu à cacher
     * @return bool Succès de l'opération
     */
    public function set(string $key, string $content): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $filePath = $this->getFilePath($key);

        // Écriture atomique avec verrou
        $tempFile = $filePath . '.tmp';
        $written = file_put_contents($tempFile, $content, LOCK_EX);

        if ($written === false) {
            return false;
        }

        return rename($tempFile, $filePath);
    }

    /**
     * Génère une clé de cache pour un composant
     *
     * @param string $name Nom du composant
     * @param array $props Props du composant
     * @return string Clé unique
     */
    public function generateKey(string $name, array $props): string
    {
        // Hash des props pour détecter les changements
        $propsHash = md5(serialize($props));

        // Nettoyer le nom pour le système de fichiers
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);

        return "component_{$safeName}_{$propsHash}";
    }

    /**
     * Invalide un fragment spécifique
     *
     * @param string $key Clé du fragment
     * @return bool Succès de l'opération
     */
    public function invalidate(string $key): bool
    {
        $filePath = $this->getFilePath($key);

        if (file_exists($filePath)) {
            return @unlink($filePath);
        }

        return true;
    }

    /**
     * Invalide tous les fragments d'un composant
     *
     * @param string $componentName Nom du composant
     * @return int Nombre de fragments invalidés
     */
    public function invalidateComponent(string $componentName): int
    {
        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return 0;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $componentName);
        $pattern = $this->cacheDir . "/component_{$safeName}_*.fragment";

        $files = glob($pattern);
        if ($files === false) {
            return 0;
        }

        $deleted = 0;
        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Nettoie tous les fragments expirés
     *
     * @return int Nombre de fragments supprimés
     */
    public function clearExpired(): int
    {
        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return 0;
        }

        $files = glob($this->cacheDir . '/*.fragment');
        if ($files === false) {
            return 0;
        }

        $deleted = 0;
        $now = time();

        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && ($now - $mtime) > $this->ttl) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Nettoie tous les fragments
     *
     * @return int Nombre de fragments supprimés
     */
    public function clearAll(): int
    {
        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return 0;
        }

        $files = glob($this->cacheDir . '/*.fragment');
        if ($files === false) {
            return 0;
        }

        $deleted = 0;
        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Statistiques du cache
     *
     * @return array{total: int, size: int, oldest: int}
     */
    public function getStats(): array
    {
        if (!$this->enabled || !is_dir($this->cacheDir)) {
            return ['total' => 0, 'size' => 0, 'oldest' => 0];
        }

        $files = glob($this->cacheDir . '/*.fragment');
        if ($files === false) {
            return ['total' => 0, 'size' => 0, 'oldest' => 0];
        }

        $total = count($files);
        $size = 0;
        $oldest = 0;

        foreach ($files as $file) {
            $size += filesize($file) ?: 0;
            $mtime = filemtime($file);
            if ($mtime !== false && ($oldest === 0 || $mtime < $oldest)) {
                $oldest = $mtime;
            }
        }

        return [
            'total' => $total,
            'size' => $size,
            'oldest' => $oldest
        ];
    }

    /**
     * Active ou désactive le cache
     *
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Vérifie si le cache est activé
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Chemin complet du fichier de cache
     *
     * @param string $key
     * @return string
     */
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . $key . '.fragment';
    }
}
