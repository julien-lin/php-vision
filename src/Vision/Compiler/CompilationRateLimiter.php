<?php

namespace JulienLinard\Vision\Compiler;

/**
 * Rate limiter pour la compilation de templates
 * 
 * Protège contre les abus de compilation en limitant le nombre de tentatives
 * de compilation par template dans une fenêtre de temps donnée.
 */
class CompilationRateLimiter
{
    private array $attempts = [];
    private int $maxAttempts;
    private int $windowSeconds;
    private bool $enabled = true;

    /**
     * @param int $maxAttempts Nombre maximum de tentatives (défaut: 10)
     * @param int $windowSeconds Fenêtre de temps en secondes (défaut: 60)
     */
    public function __construct(int $maxAttempts = 10, int $windowSeconds = 60)
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Vérifie si la limite est atteinte pour un template
     * 
     * @param string $templatePath Chemin du template
     * @return bool True si la compilation est autorisée, false si la limite est atteinte
     */
    public function checkLimit(string $templatePath): bool
    {
        if (!$this->enabled) {
            return true; // Rate limiting désactivé
        }

        $key = $this->getKey($templatePath);
        $now = time();

        // Nettoyer les anciennes tentatives
        if (isset($this->attempts[$key])) {
            $this->attempts[$key] = array_filter(
                $this->attempts[$key],
                fn($time) => ($now - $time) < $this->windowSeconds
            );
        }

        $count = count($this->attempts[$key] ?? []);

        if ($count >= $this->maxAttempts) {
            return false; // Limite atteinte
        }

        // Enregistrer la tentative
        if (!isset($this->attempts[$key])) {
            $this->attempts[$key] = [];
        }
        $this->attempts[$key][] = $now;

        // Nettoyer périodiquement les anciennes entrées
        if (count($this->attempts) > 1000) {
            $this->cleanup();
        }

        return true;
    }

    /**
     * Génère une clé unique pour un template
     * 
     * @param string $templatePath Chemin du template
     * @return string Clé unique
     */
    private function getKey(string $templatePath): string
    {
        return md5($templatePath);
    }

    /**
     * Nettoie les anciennes entrées expirées
     */
    private function cleanup(): void
    {
        $now = time();
        foreach ($this->attempts as $key => $times) {
            $this->attempts[$key] = array_filter(
                $times,
                fn($time) => ($now - $time) < $this->windowSeconds
            );
            
            // Supprimer les clés vides
            if (empty($this->attempts[$key])) {
                unset($this->attempts[$key]);
            }
        }
    }

    /**
     * Obtient le nombre de tentatives restantes pour un template
     * 
     * @param string $templatePath Chemin du template
     * @return int Nombre de tentatives restantes
     */
    public function getRemainingAttempts(string $templatePath): int
    {
        if (!$this->enabled) {
            return $this->maxAttempts;
        }

        $key = $this->getKey($templatePath);
        $now = time();

        // Nettoyer les anciennes tentatives
        if (isset($this->attempts[$key])) {
            $this->attempts[$key] = array_filter(
                $this->attempts[$key],
                fn($time) => ($now - $time) < $this->windowSeconds
            );
        }

        $count = count($this->attempts[$key] ?? []);
        return max(0, $this->maxAttempts - $count);
    }

    /**
     * Obtient le temps d'attente avant la prochaine tentative autorisée
     * 
     * @param string $templatePath Chemin du template
     * @return int Temps d'attente en secondes (0 si aucune limite)
     */
    public function getWaitTime(string $templatePath): int
    {
        if (!$this->enabled) {
            return 0;
        }

        $key = $this->getKey($templatePath);
        $now = time();

        if (!isset($this->attempts[$key]) || empty($this->attempts[$key])) {
            return 0;
        }

        // Nettoyer les anciennes tentatives
        $this->attempts[$key] = array_filter(
            $this->attempts[$key],
            fn($time) => ($now - $time) < $this->windowSeconds
        );

        if (empty($this->attempts[$key])) {
            return 0;
        }

        // Trouver la plus ancienne tentative dans la fenêtre
        $oldest = min($this->attempts[$key]);
        $waitTime = $this->windowSeconds - ($now - $oldest);

        return max(0, $waitTime);
    }

    /**
     * Réinitialise le compteur pour un template
     * 
     * @param string $templatePath Chemin du template
     */
    public function reset(string $templatePath): void
    {
        $key = $this->getKey($templatePath);
        unset($this->attempts[$key]);
    }

    /**
     * Vide tous les compteurs
     */
    public function clear(): void
    {
        $this->attempts = [];
    }

    /**
     * Active ou désactive le rate limiting
     * 
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Vérifie si le rate limiting est activé
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Définit le nombre maximum de tentatives
     * 
     * @param int $maxAttempts
     */
    public function setMaxAttempts(int $maxAttempts): void
    {
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * Définit la fenêtre de temps
     * 
     * @param int $windowSeconds
     */
    public function setWindowSeconds(int $windowSeconds): void
    {
        $this->windowSeconds = $windowSeconds;
    }

    /**
     * Obtient les statistiques du rate limiter
     * 
     * @return array{enabled: bool, max_attempts: int, window_seconds: int, tracked_templates: int}
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'max_attempts' => $this->maxAttempts,
            'window_seconds' => $this->windowSeconds,
            'tracked_templates' => count($this->attempts),
        ];
    }
}
