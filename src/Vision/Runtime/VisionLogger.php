<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Runtime;

/**
 * Logger structuré pour Vision
 * 
 * Permet de logger les événements avec contexte pour faciliter
 * le debugging et le monitoring en production.
 */
class VisionLogger implements VisionLoggerInterface
{
    /**
     * Niveaux de log valides
     */
    private const VALID_LEVELS = ['debug', 'info', 'warning', 'error'];

    /**
     * Niveau de log minimum (logs en dessous ne sont pas enregistrés)
     */
    private string $minLevel = 'info';

    /**
     * Activer le logging
     */
    private bool $enabled = true;

    /**
     * Constructeur
     * 
     * @param string $minLevel Niveau minimum (debug, info, warning, error)
     * @param bool $enabled Activer le logging
     */
    public function __construct(string $minLevel = 'info', bool $enabled = true)
    {
        $this->setMinLevel($minLevel);
        $this->enabled = $enabled;
    }

    /**
     * Enregistre un log
     * 
     * @param string $level Niveau de log (debug, info, warning, error)
     * @param string $message Message à logger
     * @param array<string, mixed> $context Contexte additionnel
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $level = strtolower($level);
        
        // Valider le niveau
        if (!in_array($level, self::VALID_LEVELS, true)) {
            $level = 'info';
        }

        // Vérifier si le niveau est suffisant
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $json = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            error_log($json);
        }
    }

    /**
     * Vérifie si un niveau de log doit être enregistré
     * 
     * @param string $level Niveau à vérifier
     * @return bool
     */
    private function shouldLog(string $level): bool
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $currentLevel = $levels[$level] ?? 1;
        $minLevel = $levels[$this->minLevel] ?? 1;
        
        return $currentLevel >= $minLevel;
    }

    /**
     * Définit le niveau minimum de log
     * 
     * @param string $level Niveau minimum
     * @return self
     */
    public function setMinLevel(string $level): self
    {
        $level = strtolower($level);
        if (in_array($level, self::VALID_LEVELS, true)) {
            $this->minLevel = $level;
        }
        return $this;
    }

    /**
     * Active ou désactive le logging
     * 
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * Vérifie si le logging est activé
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Méthodes de convenance pour chaque niveau
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
}
