<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Runtime;

use JulienLinard\Vision\Exception\VisionException;

/**
 * Mode Sandbox pour templates non-fiables
 * 
 * Permet de restreindre les fonctionnalités disponibles dans les templates
 * pour protéger contre les templates malveillants.
 */
class Sandbox
{
    /**
     * Fonctions autorisées (whitelist)
     * 
     * @var array<string>
     */
    private array $allowedFunctions = [];

    /**
     * Filtres autorisés (whitelist)
     * 
     * @var array<string>
     */
    private array $allowedFilters = [];

    /**
     * Profondeur de récursion maximale
     */
    private int $maxRecursionDepth = 20;

    /**
     * Taille maximale du template en octets
     */
    private int $maxTemplateSize = 1024 * 1024; // 1MB

    /**
     * Activer le mode sandbox strict
     */
    private bool $strictMode = false;

    /**
     * Constructeur avec configuration par défaut
     */
    public function __construct()
    {
        // Par défaut, aucune fonction ni filtre autorisé
        // L'utilisateur doit explicitement autoriser ce qu'il veut
    }

    /**
     * Valide un template selon les règles du sandbox
     * 
     * @param string $content Contenu du template
     * @throws VisionException Si le template ne respecte pas les règles
     */
    public function validateTemplate(string $content): void
    {
        // Vérifier la taille
        if (strlen($content) > $this->maxTemplateSize) {
            throw new VisionException(
                "Template too large: " . strlen($content) . " bytes (max: {$this->maxTemplateSize} bytes)"
            );
        }

        // Vérifier les fonctions autorisées
        $this->validateFunctions($content);

        // Vérifier les filtres autorisés
        $this->validateFilters($content);
    }

    /**
     * Vérifie que toutes les fonctions utilisées sont autorisées
     * 
     * @param string $content Contenu du template
     * @throws VisionException Si une fonction non autorisée est détectée
     */
    private function validateFunctions(string $content): void
    {
        // Pattern pour détecter les appels de fonctions: {{ functionName(...) }}
        if (preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches)) {
            foreach ($matches[1] as $funcName) {
                // Ignorer les variables simples (pas d'appel de fonction)
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $funcName)) {
                    continue;
                }

                // En mode strict, toutes les fonctions doivent être autorisées
                if ($this->strictMode && !in_array($funcName, $this->allowedFunctions, true)) {
                    throw new VisionException(
                        "Function '{$funcName}' not allowed in sandbox mode. " .
                        "Allowed functions: " . implode(', ', $this->allowedFunctions ?: ['none'])
                    );
                }

                // Si des fonctions sont explicitement autorisées, vérifier
                if (!empty($this->allowedFunctions) && !in_array($funcName, $this->allowedFunctions, true)) {
                    throw new VisionException(
                        "Function '{$funcName}' not allowed in sandbox mode. " .
                        "Allowed functions: " . implode(', ', $this->allowedFunctions)
                    );
                }
            }
        }
    }

    /**
     * Vérifie que tous les filtres utilisés sont autorisés
     * 
     * @param string $content Contenu du template
     * @throws VisionException Si un filtre non autorisé est détecté
     */
    private function validateFilters(string $content): void
    {
        // Pattern pour détecter les filtres: {{ variable|filter1|filter2:param }}
        if (preg_match_all('/\|\s*([a-zA-Z_][a-zA-Z0-9_]*)/', $content, $matches)) {
            foreach ($matches[1] as $filterName) {
                // En mode strict, tous les filtres doivent être autorisés
                if ($this->strictMode && !in_array($filterName, $this->allowedFilters, true)) {
                    throw new VisionException(
                        "Filter '{$filterName}' not allowed in sandbox mode. " .
                        "Allowed filters: " . implode(', ', $this->allowedFilters ?: ['none'])
                    );
                }

                // Si des filtres sont explicitement autorisés, vérifier
                if (!empty($this->allowedFilters) && !in_array($filterName, $this->allowedFilters, true)) {
                    throw new VisionException(
                        "Filter '{$filterName}' not allowed in sandbox mode. " .
                        "Allowed filters: " . implode(', ', $this->allowedFilters)
                    );
                }
            }
        }
    }

    /**
     * Définit les fonctions autorisées
     * 
     * @param array<string> $functions Liste des noms de fonctions autorisées
     * @return self
     */
    public function setAllowedFunctions(array $functions): self
    {
        $this->allowedFunctions = $functions;
        return $this;
    }

    /**
     * Ajoute une fonction autorisée
     * 
     * @param string $function Nom de la fonction
     * @return self
     */
    public function addAllowedFunction(string $function): self
    {
        if (!in_array($function, $this->allowedFunctions, true)) {
            $this->allowedFunctions[] = $function;
        }
        return $this;
    }

    /**
     * Définit les filtres autorisés
     * 
     * @param array<string> $filters Liste des noms de filtres autorisés
     * @return self
     */
    public function setAllowedFilters(array $filters): self
    {
        $this->allowedFilters = $filters;
        return $this;
    }

    /**
     * Ajoute un filtre autorisé
     * 
     * @param string $filter Nom du filtre
     * @return self
     */
    public function addAllowedFilter(string $filter): self
    {
        if (!in_array($filter, $this->allowedFilters, true)) {
            $this->allowedFilters[] = $filter;
        }
        return $this;
    }

    /**
     * Définit la profondeur de récursion maximale
     * 
     * @param int $depth Profondeur maximale
     * @return self
     */
    public function setMaxRecursionDepth(int $depth): self
    {
        $this->maxRecursionDepth = $depth;
        return $this;
    }

    /**
     * Obtient la profondeur de récursion maximale
     * 
     * @return int
     */
    public function getMaxRecursionDepth(): int
    {
        return $this->maxRecursionDepth;
    }

    /**
     * Définit la taille maximale du template
     * 
     * @param int $size Taille maximale en octets
     * @return self
     */
    public function setMaxTemplateSize(int $size): self
    {
        $this->maxTemplateSize = $size;
        return $this;
    }

    /**
     * Obtient la taille maximale du template
     * 
     * @return int
     */
    public function getMaxTemplateSize(): int
    {
        return $this->maxTemplateSize;
    }

    /**
     * Active ou désactive le mode strict
     * 
     * En mode strict, toutes les fonctions et filtres doivent être explicitement autorisés
     * 
     * @param bool $strict
     * @return self
     */
    public function setStrictMode(bool $strict): self
    {
        $this->strictMode = $strict;
        return $this;
    }

    /**
     * Vérifie si le mode strict est activé
     * 
     * @return bool
     */
    public function isStrictMode(): bool
    {
        return $this->strictMode;
    }

    /**
     * Obtient les fonctions autorisées
     * 
     * @return array<string>
     */
    public function getAllowedFunctions(): array
    {
        return $this->allowedFunctions;
    }

    /**
     * Obtient les filtres autorisés
     * 
     * @return array<string>
     */
    public function getAllowedFilters(): array
    {
        return $this->allowedFilters;
    }
}
