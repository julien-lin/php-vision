<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Compiler;

use JulienLinard\Vision\Parser\ASTNode;
use JulienLinard\Vision\Parser\NodeType;

/**
 * Élimine les branches mortes des structures conditionnelles
 * 
 * Optimise les conditions avec des valeurs constantes :
 * - {% if true %} → garde le contenu, supprime la condition
 * - {% if false %} → supprime tout le bloc
 * - {% if false %}A{% else %}B{% endif %} → garde uniquement B
 * 
 * Gains de performance : 5-10% sur templates avec conditions statiques
 */
class DeadBranchEliminator
{
    private ConstantFolder $constantFolder;

    public function __construct()
    {
        $this->constantFolder = new ConstantFolder();
    }

    /**
     * Optimise l'AST en éliminant les branches mortes
     *
     * @param ASTNode $node Nœud racine de l'AST
     * @return ASTNode AST optimisé
     */
    public function optimize(ASTNode $node): ASTNode
    {
        // Créer une copie pour ne pas modifier l'original
        $optimized = clone $node;
        $optimized->children = $this->optimizeChildren($node->children);
        return $optimized;
    }

    /**
     * Optimise récursivement les enfants d'un nœud
     *
     * @param array<ASTNode> $children
     * @return array<ASTNode>
     */
    private function optimizeChildren(array $children): array
    {
        $optimized = [];
        $i = 0;

        while ($i < count($children)) {
            $child = $children[$i];

            // Si c'est une condition IF, tenter de l'optimiser (avec ses elseif/else suivants)
            if ($child->type === NodeType::IF_CONDITION) {
                // Collecter le groupe if/elseif/else
                $ifGroup = [$child];
                $j = $i + 1;
                while ($j < count($children)) {
                    if (
                        $children[$j]->type === NodeType::ELSEIF_CONDITION ||
                        $children[$j]->type === NodeType::ELSE_CONDITION
                    ) {
                        $ifGroup[] = $children[$j];
                        $j++;
                    } else {
                        break;
                    }
                }

                // Optimiser le groupe
                $result = $this->optimizeIfGroup($ifGroup);
                if ($result === null) {
                    // Bloc entier supprimé
                    $i = $j;
                    continue;
                }

                if (is_array($result)) {
                    // Plusieurs nœuds retournés
                    $optimized = array_merge($optimized, $result);
                } else {
                    // Un seul nœud
                    $optimized[] = $result;
                }

                $i = $j;
            } else {
                // Cloner et optimiser récursivement
                $cloned = clone $child;
                $cloned->children = $this->optimizeChildren($child->children);
                $optimized[] = $cloned;
                $i++;
            }
        }

        return $optimized;
    }

    /**
     * Optimise un groupe if/elseif/else
     *
     * @param array<ASTNode> $group [IF_CONDITION, ELSEIF_CONDITION?, ELSE_CONDITION?]
     * @return ASTNode|array<ASTNode>|null
     *         - ASTNode : condition conservée (optimisée récursivement)
     *         - array<ASTNode> : condition éliminée, retourne les enfants de la branche active
     *         - null : toutes les branches éliminées (dead code)
     */
    private function optimizeIfGroup(array $group): ASTNode|array|null
    {
        $ifNode = $group[0];

        if (!isset($ifNode->metadata[1])) {
            // Pas de condition extractible, conserver le groupe
            $cloned = clone $ifNode;
            $cloned->children = $this->optimizeChildren($ifNode->children);

            // Conserver les elseif/else tels quels
            $result = [$cloned];
            for ($i = 1; $i < count($group); $i++) {
                $cloned = clone $group[$i];
                $cloned->children = $this->optimizeChildren($group[$i]->children);
                $result[] = $cloned;
            }

            return count($result) === 1 ? $result[0] : $result;
        }

        $condition = trim($ifNode->metadata[1][0]);

        // Cas spécial: true/false littéraux (déjà constants)
        if ($condition === 'true' || $condition === 'false') {
            $isTrue = ($condition === 'true');
            if ($isTrue) {
                return $this->optimizeChildren($ifNode->children);
            } else {
                // {% if false %} → chercher elseif/else
                for ($i = 1; $i < count($group); $i++) {
                    $node = $group[$i];

                    if ($node->type === NodeType::ELSEIF_CONDITION) {
                        if (!isset($node->metadata[1])) {
                            $cloned = clone $node;
                            $cloned->children = $this->optimizeChildren($node->children);

                            $result = [$cloned];
                            for ($j = $i + 1; $j < count($group); $j++) {
                                $cloned = clone $group[$j];
                                $cloned->children = $this->optimizeChildren($group[$j]->children);
                                $result[] = $cloned;
                            }

                            return count($result) === 1 ? $result[0] : $result;
                        }

                        $elseifCond = trim($node->metadata[1][0]);

                        // Cas spécial elseif true/false
                        if ($elseifCond === 'true') {
                            return $this->optimizeChildren($node->children);
                        } elseif ($elseifCond === 'false') {
                            // Continuer vers le prochain elseif/else
                            continue;
                        }

                        // Sinon, tenter fold
                        $elseifFolded = $this->constantFolder->fold($elseifCond);

                        if ($elseifFolded !== $elseifCond) {
                            $elseifTrue = ($elseifFolded === 'true' || $elseifFolded === '1');
                            if ($elseifTrue) {
                                return $this->optimizeChildren($node->children);
                            }
                        } else {
                            // Non constante, conserver
                            $cloned = clone $node;
                            $cloned->children = $this->optimizeChildren($node->children);

                            $result = [$cloned];
                            for ($j = $i + 1; $j < count($group); $j++) {
                                $cloned = clone $group[$j];
                                $cloned->children = $this->optimizeChildren($group[$j]->children);
                                $result[] = $cloned;
                            }

                            return count($result) === 1 ? $result[0] : $result;
                        }
                    } elseif ($node->type === NodeType::ELSE_CONDITION) {
                        return $this->optimizeChildren($node->children);
                    }
                }

                // Aucun elseif/else, dead code
                return null;
            }
        }

        $folded = $this->constantFolder->fold($condition);

        // Si non constante, conserver le groupe
        if ($folded === $condition) {
            $cloned = clone $ifNode;
            $cloned->children = $this->optimizeChildren($ifNode->children);

            $result = [$cloned];
            for ($i = 1; $i < count($group); $i++) {
                $cloned = clone $group[$i];
                $cloned->children = $this->optimizeChildren($group[$i]->children);
                $result[] = $cloned;
            }

            return count($result) === 1 ? $result[0] : $result;
        }

        // Condition constante
        $isTrue = ($folded === 'true' || $folded === '1');

        if ($isTrue) {
            // {% if true %} → retourner uniquement le contenu du if
            return $this->optimizeChildren($ifNode->children);
        } else {
            // {% if false %} → chercher elseif/else
            for ($i = 1; $i < count($group); $i++) {
                $node = $group[$i];

                if ($node->type === NodeType::ELSEIF_CONDITION) {
                    // Optimiser l'elseif comme un if
                    if (!isset($node->metadata[1])) {
                        // Pas de condition, conserver
                        $cloned = clone $node;
                        $cloned->children = $this->optimizeChildren($node->children);

                        $result = [$cloned];
                        for ($j = $i + 1; $j < count($group); $j++) {
                            $cloned = clone $group[$j];
                            $cloned->children = $this->optimizeChildren($group[$j]->children);
                            $result[] = $cloned;
                        }

                        return count($result) === 1 ? $result[0] : $result;
                    }

                    $elseifCond = trim($node->metadata[1][0]);
                    $elseifFolded = $this->constantFolder->fold($elseifCond);

                    if ($elseifFolded !== $elseifCond) {
                        // Constante
                        $elseifTrue = ($elseifFolded === 'true' || $elseifFolded === '1');
                        if ($elseifTrue) {
                            return $this->optimizeChildren($node->children);
                        }
                        // elseif false, continuer
                    } else {
                        // Non constante, conserver elseif et le reste
                        $cloned = clone $node;
                        $cloned->children = $this->optimizeChildren($node->children);

                        $result = [$cloned];
                        for ($j = $i + 1; $j < count($group); $j++) {
                            $cloned = clone $group[$j];
                            $cloned->children = $this->optimizeChildren($group[$j]->children);
                            $result[] = $cloned;
                        }

                        return count($result) === 1 ? $result[0] : $result;
                    }
                } elseif ($node->type === NodeType::ELSE_CONDITION) {
                    // Retourner le contenu du else
                    return $this->optimizeChildren($node->children);
                }
            }

            // Aucun elseif/else, dead code
            return null;
        }
    }

    /**
     * Optimise une condition IF
     *
     * @param ASTNode $node Nœud IF_CONDITION
     * @return ASTNode|array<ASTNode>|null
     *         - ASTNode : condition conservée (optimisée récursivement)
     *         - array<ASTNode> : condition éliminée, retourne les enfants de la branche active
     *         - null : condition éliminée, branche false sans else (dead code)
     * @deprecated Use optimizeIfGroup instead
     */
    private function optimizeIfCondition(ASTNode $node): ASTNode|array|null
    {
        if (!isset($node->metadata[1])) {
            // Pas de condition extractible, conserver le nœud
            $cloned = clone $node;
            $cloned->children = $this->optimizeChildren($node->children);
            return $cloned;
        }

        $condition = trim($node->metadata[1][0]);

        // Tenter de fold la condition
        $folded = $this->constantFolder->fold($condition);

        // Si la condition n'a pas changé, elle n'est pas constante
        if ($folded === $condition) {
            // Conserver le nœud mais optimiser ses enfants
            $cloned = clone $node;
            $cloned->children = $this->optimizeChildren($node->children);
            return $cloned;
        }

        // La condition est constante
        $isTrue = ($folded === 'true' || $folded === '1');

        if ($isTrue) {
            // {% if true %} → retourner uniquement le contenu du if
            return $this->extractBranchContent($node, 'if');
        } else {
            // {% if false %} → retourner le contenu du else/elseif, ou null si aucun
            return $this->extractFalseBranch($node);
        }
    }

    /**
     * Extrait le contenu d'une branche (if, elseif, else)
     *
     * @param ASTNode $node Nœud de condition
     * @param string $branchType 'if', 'elseif', ou 'else'
     * @return array<ASTNode> Enfants de la branche
     */
    private function extractBranchContent(ASTNode $node, string $branchType): array
    {
        $content = [];

        foreach ($node->children as $child) {
            // Ignorer les elseif/else si on extrait la branche if
            if ($branchType === 'if') {
                if ($child->type === NodeType::ELSEIF_CONDITION || $child->type === NodeType::ELSE_CONDITION) {
                    break; // Stop dès qu'on atteint elseif/else
                }
            }

            // Optimiser récursivement et ajouter
            $cloned = clone $child;
            $cloned->children = $this->optimizeChildren($child->children);
            $content[] = $cloned;
        }

        return $content;
    }

    /**
     * Extrait la branche false (elseif/else) d'une condition
     *
     * @param ASTNode $node Nœud IF_CONDITION
     * @return array<ASTNode>|null
     */
    private function extractFalseBranch(ASTNode $node): ?array
    {
        // Chercher les elseif et else
        foreach ($node->children as $child) {
            if ($child->type === NodeType::ELSEIF_CONDITION) {
                // Essayer d'optimiser ce elseif comme un if
                $result = $this->optimizeIfCondition($child);
                if ($result !== null) {
                    return is_array($result) ? $result : [$result];
                }
            } elseif ($child->type === NodeType::ELSE_CONDITION) {
                // Retourner le contenu du else
                return $this->extractElseContent($child);
            }
        }

        // Pas de else/elseif, la condition entière est dead code
        return null;
    }

    /**
     * Extrait le contenu d'un else
     *
     * @param ASTNode $node Nœud ELSE_CONDITION
     * @return array<ASTNode>
     */
    private function extractElseContent(ASTNode $node): array
    {
        $content = [];

        foreach ($node->children as $child) {
            $cloned = clone $child;
            $cloned->children = $this->optimizeChildren($child->children);
            $content[] = $cloned;
        }

        return $content;
    }

    /**
     * Analyse le potentiel d'optimisation d'un AST
     *
     * @param ASTNode $node
     * @return array{eliminable: int, total: int, percentage: float}
     */
    public function analyzeOptimizationPotential(ASTNode $node): array
    {
        $stats = ['eliminable' => 0, 'total' => 0];
        $this->collectIfStats($node, $stats);

        $percentage = $stats['total'] > 0
            ? round(($stats['eliminable'] / $stats['total']) * 100, 2)
            : 0.0;

        return [
            'eliminable' => $stats['eliminable'],
            'total' => $stats['total'],
            'percentage' => $percentage
        ];
    }

    /**
     * Collecte les statistiques sur les conditions IF
     *
     * @param ASTNode $node
     * @param array<string, int> $stats
     */
    private function collectIfStats(ASTNode $node, array &$stats): void
    {
        if ($node->type === NodeType::IF_CONDITION) {
            $stats['total']++;

            if (isset($node->metadata[1])) {
                $condition = trim($node->metadata[1][0]);

                // true/false littéraux sont toujours éliminables
                if ($condition === 'true' || $condition === 'false') {
                    $stats['eliminable']++;
                } else {
                    // Sinon, tenter de fold
                    $folded = $this->constantFolder->fold($condition);
                    if ($folded !== $condition) {
                        $stats['eliminable']++;
                    }
                }
            }
        }

        foreach ($node->children as $child) {
            $this->collectIfStats($child, $stats);
        }
    }
}
