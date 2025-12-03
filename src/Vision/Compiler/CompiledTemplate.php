<?php

namespace JulienLinard\Vision\Compiler;

use JulienLinard\Vision\Parser\ParsedTemplate;

/**
 * Représente un template compilé en code PHP
 */
class CompiledTemplate
{
    /**
     * @param string $phpCode Code PHP généré
     * @param ParsedTemplate $parsed Template parsé original
     */
    public function __construct(
        public readonly string $phpCode,
        public readonly ParsedTemplate $parsed
    ) {}

    /**
     * Sauvegarde le code compilé dans un fichier
     */
    public function saveToFile(string $filePath): bool
    {
        return file_put_contents($filePath, $this->phpCode) !== false;
    }

    /**
     * Exécute le code compilé avec les variables données
     * 
     * @param array<string, mixed> $__variables Variables disponibles
     * @return string Résultat du rendu
     */
    public function execute(array $__variables = [], array $__helpers = []): string
    {
        // Mode debug supprimé pour la production

        // Utiliser une closure pour isoler l'exécution
        $executor = function () use ($__variables, $__helpers) {
            return eval('?>' . $this->phpCode);
        };

        return $executor();
    }
}
