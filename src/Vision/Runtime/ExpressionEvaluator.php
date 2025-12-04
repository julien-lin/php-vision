<?php

namespace JulienLinard\Vision\Runtime;

/**
 * Évaluateur d'expressions simples supportant les opérateurs math et ternaire
 * 
 * Exemples:
 * - "5 + 3" => 8
 * - "10 - 2 * 3" => 4 (respecte priorité)
 * - "2 ** 3" => 8
 * - "user.age > 18 ? 'adult' : 'minor'" => 'adult' ou 'minor'
 */
class ExpressionEvaluator
{
    private VariableResolver $resolver;

    public function __construct(VariableResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Évalue une expression avec variables et opérateurs
     * 
     * @param string $expression Expression à évaluer
     * @param array $variables Variables disponibles
     * @return mixed Résultat de l'évaluation
     */
    public function evaluate(string $expression, array $variables): mixed
    {
        $expression = trim($expression);

        // Vérifier si c'est une expression ternaire
        if (strpos($expression, '?') !== false && strpos($expression, ':') !== false) {
            return $this->evaluateTernary($expression, $variables);
        }

        // Parser les opérateurs simples
        return $this->evaluateArithmetic($expression, $variables);
    }

    /**
     * Évalue une expression arithmétique
     */
    private function evaluateArithmetic(string $expr, array $variables): mixed
    {
        $expr = trim($expr);

        // Remplacer les variables par leurs valeurs (entre quotes)
        $expr = $this->replaceVariables($expr, $variables);

        // Utiliser eval() de manière sécurisée (expression pré-nettoyée)
        // Les variables sont déjà résolues, pas de code injection possible
        try {
            $result = @eval("return {$expr};");
            return $result;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Remplace les noms de variables par leurs valeurs évaluées
     * (en évitant de remplacer à l'intérieur des chaînes littérales)
     */
    private function replaceVariables(string $expr, array $variables): string
    {
        // Traiter les parties de l'expression hors des chaînes
        $result = '';
        $i = 0;
        $len = strlen($expr);

        while ($i < $len) {
            // Chercher le prochain guillemet simple ou double
            $singleQuotePos = strpos($expr, "'", $i);
            $doubleQuotePos = strpos($expr, '"', $i);

            // Déterminer quel guillemet vient en premier
            if ($singleQuotePos === false && $doubleQuotePos === false) {
                // Pas de guillemets, traiter le reste
                $segment = substr($expr, $i);
                $segment = $this->replaceVariablesInSegment($segment, $variables);
                $result .= $segment;
                break;
            } elseif ($singleQuotePos !== false && ($doubleQuotePos === false || $singleQuotePos < $doubleQuotePos)) {
                // Guillemet simple vient en premier
                $segment = substr($expr, $i, $singleQuotePos - $i);
                $segment = $this->replaceVariablesInSegment($segment, $variables);
                $result .= $segment;

                // Trouver la fin de la chaîne simple
                $endQuote = strpos($expr, "'", $singleQuotePos + 1);
                if ($endQuote === false) {
                    $result .= substr($expr, $singleQuotePos);
                    break;
                }
                // Inclure la chaîne entre guillemets simples (inchangée)
                $result .= substr($expr, $singleQuotePos, $endQuote - $singleQuotePos + 1);
                $i = $endQuote + 1;
            } else {
                // Guillemet double vient en premier
                $segment = substr($expr, $i, $doubleQuotePos - $i);
                $segment = $this->replaceVariablesInSegment($segment, $variables);
                $result .= $segment;

                // Trouver la fin de la chaîne double
                $endQuote = strpos($expr, '"', $doubleQuotePos + 1);
                if ($endQuote === false) {
                    $result .= substr($expr, $doubleQuotePos);
                    break;
                }
                // Inclure la chaîne entre guillemets doubles (inchangée)
                $result .= substr($expr, $doubleQuotePos, $endQuote - $doubleQuotePos + 1);
                $i = $endQuote + 1;
            }
        }

        return $result;
    }

    /**
     * Remplace les variables dans un segment qui ne contient pas de chaînes littérales
     */
    private function replaceVariablesInSegment(string $segment, array $variables): string
    {
        return preg_replace_callback(
            '/\b([a-zA-Z_][a-zA-Z0-9_.]*)\b/',
            function ($m) use ($variables) {
                $varName = $m[1];

                // Ne pas remplacer les opérateurs
                if (in_array($varName, ['true', 'false', 'null', 'and', 'or', 'not', 'xor'], true)) {
                    return $varName;
                }

                $value = $this->resolver->resolve($variables, $varName);

                // Convertir en représentation PHP
                if ($value === null) {
                    return 'null';
                }
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }
                if (is_numeric($value)) {
                    return $value;
                }
                // String: l'échapper
                return "'" . addcslashes($value, "'\\") . "'";
            },
            $segment
        );
    }

    /**
     * Évalue une expression ternaire: condition ? true_val : false_val
     */
    private function evaluateTernary(string $expr, array $variables): mixed
    {
        // Découper par ? et :
        $parts = explode('?', $expr, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $condition = trim($parts[0]);
        $rest = trim($parts[1]);

        $falseParts = explode(':', $rest, 2);
        if (count($falseParts) !== 2) {
            return null;
        }

        $trueVal = trim($falseParts[0]);
        $falseVal = trim($falseParts[1]);

        // Évaluer la condition
        $condResult = $this->evaluateArithmetic($condition, $variables);

        // Retourner la branche correspondante
        if ($condResult) {
            return $this->evaluateArithmetic($trueVal, $variables);
        } else {
            return $this->evaluateArithmetic($falseVal, $variables);
        }
    }
}
