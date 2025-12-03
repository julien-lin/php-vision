<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Runtime;

/**
 * Interface pour le logging Vision
 */
interface VisionLoggerInterface
{
    /**
     * Enregistre un log
     * 
     * @param string $level Niveau de log (debug, info, warning, error)
     * @param string $message Message à logger
     * @param array<string, mixed> $context Contexte additionnel
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void;
}
