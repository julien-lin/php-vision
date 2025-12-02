<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Compiler;

/**
 * Constant Folder - Optimise les expressions constantes au moment de la compilation
 * 
 * Pré-calcule les expressions qui ne dépendent pas de variables:
 * - Opérations mathématiques: 24 * 60 * 60 → 86400
 * - Concaténations de chaînes: "Hello " ~ "World" → "Hello World"
 * - Expressions booléennes: true && false → false
 */
class ConstantFolder
{
    /**
     * Optimise une expression en pré-calculant les constantes
     *
     * @param string $expression Expression à optimiser
     * @return string Expression optimisée ou originale si non optimisable
     */
    public function fold(string $expression): string
    {
        $expression = trim($expression);
        
        // Ignorer expressions avec variables
        if ($this->containsVariables($expression)) {
            return $expression;
        }
        
        // Tenter de fold les opérations mathématiques
        $folded = $this->foldMathematical($expression);
        if ($folded !== null) {
            return $this->formatResult($folded);
        }
        
        // Tenter de fold les concaténations de chaînes
        $folded = $this->foldStringConcatenation($expression);
        if ($folded !== null) {
            return $this->formatResult($folded);
        }
        
        // Tenter de fold les expressions booléennes
        $folded = $this->foldBoolean($expression);
        if ($folded !== null) {
            return $this->formatResult($folded);
        }
        
        return $expression;
    }

    /**
     * Vérifie si l'expression contient des variables
     *
     * @param string $expression
     * @return bool
     */
    private function containsVariables(string $expression): bool
    {
        // Détecter variables (lettres suivies de alphanum ou underscore)
        // mais exclure true/false/null
        if (preg_match('/\b(?!true\b|false\b|null\b)[a-zA-Z_][a-zA-Z0-9_]*\b/', $expression)) {
            return true;
        }
        
        // Détecter notation pointée (user.name)
        if (preg_match('/[a-zA-Z_][a-zA-Z0-9_]*\.[a-zA-Z_]/', $expression)) {
            return true;
        }
        
        return false;
    }

    /**
     * Fold les opérations mathématiques
     *
     * @param string $expression
     * @return int|float|null
     */
    private function foldMathematical(string $expression): int|float|null
    {
        // Supporter: +, -, *, /, %, ** (puissance)
        // Pattern: nombre opérateur nombre (récursif)
        
        // Remplacer espaces
        $expr = str_replace(' ', '', $expression);
        
        // Vérifier que c'est une expression mathématique valide
        if (!preg_match('/^[\d+\-*\/%().\s]+$/', $expr)) {
            return null;
        }
        
        // Évaluer de manière sécurisée
        try {
            // Validation stricte: seulement nombres et opérateurs
            if (preg_match('/[^0-9+\-*\/%().\s]/', $expr)) {
                return null;
            }
            
            // Utiliser eval avec précaution (seulement après validation)
            $result = @eval("return $expr;");
            
            if (is_numeric($result)) {
                return $result;
            }
        } catch (\Throwable $e) {
            // Expression invalide, retourner null
        }
        
        return null;
    }

    /**
     * Fold les concaténations de chaînes
     *
     * @param string $expression
     * @return string|null
     */
    private function foldStringConcatenation(string $expression): ?string
    {
        // Détecter pattern: "string1" ~ "string2" ou 'string1' . 'string2'
        
        // Vérifier si l'expression contient des opérateurs de concat
        if (!preg_match('/[~.]/', $expression)) {
            return null;
        }
        
        // Extraire toutes les chaînes entre guillemets (simples ou doubles)
        if (preg_match_all('/(["\'])([^"\']*)\1/', $expression, $matches)) {
            // Vérifier que toute l'expression est composée de chaînes et d'opérateurs
            $cleaned = preg_replace('/(["\'])([^"\']*)\1/', '', $expression);
            $cleaned = preg_replace('/[~.\s]/', '', $cleaned);
            
            if ($cleaned === '') {
                // Toute l'expression est des chaînes + opérateurs
                return implode('', $matches[2]);
            }
        }
        
        return null;
    }

    /**
     * Fold les expressions booléennes
     *
     * @param string $expression
     * @return bool|null
     */
    private function foldBoolean(string $expression): ?bool
    {
        $expr = trim($expression);
        
        // Valeurs littérales
        if ($expr === 'true') {
            return true;
        }
        if ($expr === 'false') {
            return false;
        }
        
        // Opérateurs logiques: &&, ||, !
        if (preg_match('/^(true|false)(\s*(&&|\|\|)\s*(true|false))+$/', $expr)) {
            // Remplacer true/false par 1/0 pour évaluation sûre
            $evalExpr = str_replace(['true', 'false'], ['1', '0'], $expr);
            $evalExpr = str_replace(['&&', '||'], ['and', 'or'], $evalExpr);
            
            try {
                $result = @eval("return (bool)($evalExpr);");
                if (is_bool($result)) {
                    return $result;
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }
        
        // Négation simple
        if (preg_match('/^!\s*(true|false)$/', $expr, $matches)) {
            return $matches[1] === 'false';
        }
        
        return null;
    }

    /**
     * Formate le résultat pour insertion dans le code
     *
     * @param mixed $result
     * @return string
     */
    private function formatResult(mixed $result): string
    {
        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }
        
        if (is_string($result)) {
            // Échapper pour insertion dans code PHP
            return "'" . addslashes($result) . "'";
        }
        
        if (is_int($result) || is_float($result)) {
            return (string)$result;
        }
        
        return 'null';
    }

    /**
     * Détecte si une expression est optimisable
     *
     * @param string $expression
     * @return bool
     */
    public function isOptimizable(string $expression): bool
    {
        if ($this->containsVariables($expression)) {
            return false;
        }
        
        // Vérifier si c'est une expression mathématique, string concat, ou booléenne
        $expr = str_replace(' ', '', $expression);
        
        // Mathématique
        if (preg_match('/^[\d+\-*\/%().]+$/', $expr)) {
            return true;
        }
        
        // String concat - vérifier présence de quotes et opérateurs
        if (preg_match('/["\'].*["\']/', $expression) && preg_match('/[~.]/', $expression)) {
            return true;
        }
        
        // Booléenne
        if (preg_match('/^(true|false|!)/', $expr)) {
            return true;
        }
        
        return false;
    }

    /**
     * Statistiques d'optimisation
     *
     * @param array<string> $expressions Liste d'expressions
     * @return array{optimized: int, total: int, percentage: float}
     */
    public function analyzeOptimizationPotential(array $expressions): array
    {
        $total = count($expressions);
        $optimized = 0;
        
        foreach ($expressions as $expr) {
            if ($this->isOptimizable($expr)) {
                $optimized++;
            }
        }
        
        $percentage = $total > 0 ? ($optimized / $total) * 100 : 0;
        
        return [
            'optimized' => $optimized,
            'total' => $total,
            'percentage' => round($percentage, 2)
        ];
    }
}
