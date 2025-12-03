<?php

namespace JulienLinard\Vision\Compiler;

use JulienLinard\Vision\Parser\ParsedTemplate;
use JulienLinard\Vision\Parser\ASTNode;
use JulienLinard\Vision\Parser\NodeType;
use JulienLinard\Vision\Exception\VisionException;

/**
 * Compiler pour les templates Vision
 * 
 * Responsabilité: Compiler un template parsé en code PHP exécutable
 * Ceci permet un cache efficace et une meilleure performance
 */
class TemplateCompiler
{
    private ConstantFolder $constantFolder;
    private FilterInliner $filterInliner;
    private DeadBranchEliminator $branchEliminator;
    private ?CompilationRateLimiter $rateLimiter = null;

    public function __construct()
    {
        $this->constantFolder = new ConstantFolder();
        $this->filterInliner = new FilterInliner();
        $this->branchEliminator = new DeadBranchEliminator();
    }

    /**
     * Définit le rate limiter pour la compilation
     * 
     * @param CompilationRateLimiter|null $rateLimiter
     */
    public function setRateLimiter(?CompilationRateLimiter $rateLimiter): void
    {
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Obtient le rate limiter
     * 
     * @return CompilationRateLimiter|null
     */
    public function getRateLimiter(): ?CompilationRateLimiter
    {
        return $this->rateLimiter;
    }
    /**
     * Compile un template parsé en code PHP
     * 
     * @param ParsedTemplate $parsed Template parsé
     * @param string|null $templatePath Chemin du template (pour rate limiting)
     * @return CompiledTemplate Template compilé avec le code PHP
     * @throws VisionException Si le rate limit est atteint
     */
    public function compile(ParsedTemplate $parsed, ?string $templatePath = null): CompiledTemplate
    {
        // Vérifier le rate limit si un template path est fourni
        if ($templatePath !== null && $this->rateLimiter !== null) {
            if (!$this->rateLimiter->checkLimit($templatePath)) {
                $waitTime = $this->rateLimiter->getWaitTime($templatePath);
                throw new VisionException(
                    "Rate limit atteint pour la compilation du template '{$templatePath}'. " .
                    "Attendez {$waitTime} secondes avant de réessayer."
                );
            }
        }

        // Optimiser l'AST en éliminant les branches mortes
        $optimizedAST = $this->branchEliminator->optimize($parsed->ast);

        $phpCode = $this->compileAST($optimizedAST);

        return new CompiledTemplate(
            $phpCode,
            $parsed
        );
    }

    /**
     * Compile l'AST en code PHP
     * 
     * @param ASTNode $node Nœud racine de l'AST
     * @return string Code PHP généré
     */
    private function compileAST(ASTNode $node): string
    {
        $code = "<?php\n";
        $code .= "// Template compilé - Ne pas modifier manuellement\n";
        $code .= "// Généré le " . date('Y-m-d H:i:s') . "\n\n";
        $code .= "\$__output = [];\n";
        $code .= "// Helpers attendus: resolveVariable, applyFilter, evaluateCondition\n";
        $code .= "if (!isset(\$__helpers) || !is_array(\$__helpers)) { throw new \\RuntimeException('Helpers manquants'); }\n\n";

        foreach ($node->children as $child) {
            $code .= $this->compileNode($child, 0);
        }

        $code .= "\nreturn implode('', \$__output);\n";

        return $code;
    }

    /**
     * Compile un nœud de l'AST
     * 
     * @param ASTNode $node Le nœud à compiler
     * @param int $indent Niveau d'indentation
     * @return string Code PHP généré
     */
    private function compileNode(ASTNode $node, int $indent): string
    {
        $indentStr = str_repeat('    ', $indent);

        return match ($node->type) {
            NodeType::TEXT => $this->compileText($node, $indentStr),
            NodeType::VARIABLE => $this->compileVariable($node, $indentStr),
            NodeType::FOR_LOOP => $this->compileForLoop($node, $indent),
            NodeType::IF_CONDITION => $this->compileIfCondition($node, $indent),
            NodeType::ELSEIF_CONDITION => $this->compileElseIfCondition($node, $indent),
            NodeType::ELSE_CONDITION => $this->compileElseCondition($node, $indent),
            NodeType::ROOT => $this->compileChildren($node, $indent),
            default => ''
        };
    }

    /**
     * Compile un nœud de texte
     */
    private function compileText(ASTNode $node, string $indent): string
    {
        $escapedText = addcslashes($node->value, "'\\");
        return "{$indent}\$__output[] = '{$escapedText}';\n";
    }

    /**
     * Compile une variable
     */
    private function compileVariable(ASTNode $node, string $indent): string
    {
        $code = '';

        // Extraire le nom de variable et les filtres depuis metadata
        if (isset($node->metadata[1])) {
            $varName = $node->metadata[1][0];
            $filterChain = isset($node->metadata[2]) ? $node->metadata[2][0] : null;

            // Optimisation: vérifier si c'est une expression constante
            $optimized = $this->constantFolder->fold($varName);

            if ($optimized !== $varName && $this->constantFolder->isOptimizable($varName)) {
                // Expression constante optimisée: insérer directement
                $code .= "{$indent}// Constant folded: {$varName} -> {$optimized}\n";
                $code .= "{$indent}\$__value = {$optimized};\n";
            } else {
                // Variable dynamique: résolution normale
                $code .= "{$indent}\$__value = \$__helpers['resolveVariable']('{$varName}', \$__variables);\n";
            }

            // Appliquer les filtres si présents
            if ($filterChain !== null && $filterChain !== '') {
                $filters = explode('|', $filterChain);
                $filters = array_map('trim', $filters);
                $filters = array_filter($filters); // Remove empty

                // Utiliser FilterInliner pour optimiser les filtres
                $code .= $this->filterInliner->compileFilterChain('$__value', $filters, $indent);
            }

            // Ajouter à l'output
            $code .= "{$indent}\$__output[] = \$__value;\n";
        }

        return $code;
    }

    /**
     * Compile une boucle for
     */
    private function compileForLoop(ASTNode $node, int $indent): string
    {
        $indentStr = str_repeat('    ', $indent);
        $code = '';

        // Extraire les informations de la boucle
        if (isset($node->metadata[1]) && isset($node->metadata[2])) {
            $itemVar = $node->metadata[1][0];
            $arrayVar = $node->metadata[2][0];
            $condition = isset($node->metadata[3]) ? trim($node->metadata[3][0]) : null;

            $code .= "{$indentStr}\$__array = \$__helpers['resolveVariable']('{$arrayVar}', \$__variables);\n";
            $code .= "{$indentStr}if (is_array(\$__array) || \$__array instanceof \\Traversable) {\n";

            if ($condition !== null) {
                $code .= "{$indentStr}    \$__filtered = [];\n";
                $code .= "{$indentStr}    foreach (\$__array as \$__item) {\n";
                $code .= "{$indentStr}        \$__variables['{$itemVar}'] = \$__item;\n";
                $code .= "{$indentStr}        if (\$__helpers['evaluateCondition']('{$condition}', \$__variables)) {\n";
                $code .= "{$indentStr}            \$__filtered[] = \$__item;\n";
                $code .= "{$indentStr}        }\n";
                $code .= "{$indentStr}    }\n";
                $code .= "{$indentStr}    \$__array = \$__filtered;\n";
            }

            $code .= "{$indentStr}    foreach (\$__array as \$__item) {\n";
            $code .= "{$indentStr}        \$__variables['{$itemVar}'] = \$__item;\n";

            // Compiler les enfants
            foreach ($node->children as $child) {
                $code .= $this->compileNode($child, $indent + 2);
            }

            $code .= "{$indentStr}    }\n";
            $code .= "{$indentStr}}\n";
        }

        return $code;
    }

    /**
     * Compile une condition if
     */
    private function compileIfCondition(ASTNode $node, int $indent): string
    {
        $indentStr = str_repeat('    ', $indent);
        $code = '';

        if (isset($node->metadata[1])) {
            $condition = trim($node->metadata[1][0]);
            $escapedCondition = addcslashes($condition, "'\\");

            $code .= "{$indentStr}if (\$__helpers['evaluateCondition']('{$escapedCondition}', \$__variables)) {\n";

            // Compiler les enfants (corps du if)
            foreach ($node->children as $child) {
                // Skip elseif et else, ils sont gérés séparément
                if ($child->type === NodeType::ELSEIF_CONDITION || $child->type === NodeType::ELSE_CONDITION) {
                    break;
                }
                $code .= $this->compileNode($child, $indent + 1);
            }

            $code .= "{$indentStr}}";

            // Gérer les elseif et else
            $foundElse = false;
            foreach ($node->children as $child) {
                if ($child->type === NodeType::ELSEIF_CONDITION) {
                    $code .= $this->compileElseIfCondition($child, $indent);
                } elseif ($child->type === NodeType::ELSE_CONDITION) {
                    $code .= $this->compileElseCondition($child, $indent);
                    $foundElse = true;
                    break;
                }
            }

            $code .= "\n";
        }

        return $code;
    }

    /**
     * Compile une condition elseif
     */
    private function compileElseIfCondition(ASTNode $node, int $indent): string
    {
        $indentStr = str_repeat('    ', $indent);
        $code = '';

        if (isset($node->metadata[1])) {
            $condition = trim($node->metadata[1][0]);
            $escapedCondition = addcslashes($condition, "'\\");

            $code .= " elseif (\$__helpers['evaluateCondition']('{$escapedCondition}', \$__variables)) {\n";

            foreach ($node->children as $child) {
                $code .= $this->compileNode($child, $indent + 1);
            }

            $code .= "{$indentStr}}";
        }

        return $code;
    }

    /**
     * Compile une condition else
     */
    private function compileElseCondition(ASTNode $node, int $indent): string
    {
        $indentStr = str_repeat('    ', $indent);
        $code = " else {\n";

        foreach ($node->children as $child) {
            $code .= $this->compileNode($child, $indent + 1);
        }

        $code .= "{$indentStr}}";

        return $code;
    }

    /**
     * Compile tous les enfants d'un nœud
     */
    private function compileChildren(ASTNode $node, int $indent): string
    {
        $code = '';
        foreach ($node->children as $child) {
            $code .= $this->compileNode($child, $indent);
        }
        return $code;
    }
}
