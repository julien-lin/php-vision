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
    private ?InheritanceResolver $inheritanceResolver = null;
    private ?MacroProcessor $macroProcessor = null;

    /**
     * Registre des macros pour le template en cours de compilation
     */
    private ?MacroRegistry $currentMacros = null;

    public function __construct()
    {
        $this->constantFolder = new ConstantFolder();
        $this->filterInliner = new FilterInliner();
        $this->branchEliminator = new DeadBranchEliminator();
    }

    /**
     * Définit le résolveur d'héritage
     * 
     * @param InheritanceResolver|null $resolver
     */
    public function setInheritanceResolver(?InheritanceResolver $resolver): void
    {
        $this->inheritanceResolver = $resolver;
    }

    /**
     * Définit le processeur de macros
     * 
     * @param MacroProcessor|null $processor
     */
    public function setMacroProcessor(?MacroProcessor $processor): void
    {
        $this->macroProcessor = $processor;
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
     * @param string|null $templatePath Chemin du template (pour rate limiting et héritage)
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

        // Résoudre l'héritage si un resolver est configuré
        $ast = $parsed->ast;
        if ($this->inheritanceResolver !== null && $templatePath !== null) {
            $ast = $this->inheritanceResolver->resolve($ast, $templatePath);
        }

        // Traiter les macros si un processeur est configuré
        if ($this->macroProcessor !== null && $templatePath !== null) {
            // Extraire les macros définies dans ce template
            $this->currentMacros = $this->macroProcessor->extractMacros($ast);

            // Traiter les imports de macros
            $this->macroProcessor->processImports($ast, $this->currentMacros, $templatePath);

            // Supprimer les définitions de macros de l'AST (elles ne doivent pas être rendues)
            $ast = $this->macroProcessor->removeMacroDefinitions($ast);
        }

        // Optimiser l'AST en éliminant les branches mortes
        $optimizedAST = $this->branchEliminator->optimize($ast);

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
            NodeType::BLOCK => $this->compileBlock($node, $indent),
            NodeType::EXTENDS => '', // extends déjà résolu par InheritanceResolver
            NodeType::PARENT => '', // parent() déjà résolu par InheritanceResolver
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
            $varName = trim($node->metadata[1][0]);
            $filterChain = isset($node->metadata[2]) ? $node->metadata[2][0] : null;

            // Vérifier si c'est un appel de macro: macroName(...) ou alias.macroName(...)
            if (preg_match('/^(\w+(?:\.\w+)?)\s*\(([^)]*)\)$/', $varName, $matches)) {
                return $this->compileMacroCall($matches[1], $matches[2], $indent);
            }

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
     * Compile un block (déjà résolu par InheritanceResolver)
     * 
     * Les blocks sont transparents à la compilation: on compile simplement leur contenu
     */
    private function compileBlock(ASTNode $node, int $indent): string
    {
        $code = '';

        foreach ($node->children as $child) {
            $code .= $this->compileNode($child, $indent);
        }

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

    /**
     * Compile un appel de macro
     * 
     * @param string $macroRef Référence de la macro (ex: "input" ou "forms.input")
     * @param string $argsString Arguments sous forme de string (ex: "name, value, type='text'")
     * @param string $indent Indentation
     * @return string Code PHP compilé
     */
    private function compileMacroCall(string $macroRef, string $argsString, string $indent): string
    {
        if ($this->currentMacros === null) {
            throw new VisionException("Cannot call macro '{$macroRef}': no macros registered");
        }

        $code = "{$indent}// Macro call: {$macroRef}({$argsString})\n";

        // Déterminer si c'est un appel de macro locale ou importée
        $macroDefinition = null;
        if (str_contains($macroRef, '.')) {
            // Macro importée: alias.macroName
            [$alias, $macroName] = explode('.', $macroRef, 2);
            $macroDefinition = $this->currentMacros->getImported($alias, $macroName);
        } else {
            // Macro locale
            $macroDefinition = $this->currentMacros->get($macroRef);
        }

        // Parser les arguments de l'appel
        $args = $this->parseMacroCallArguments($argsString);

        // Binder les arguments aux paramètres de la macro
        $boundArgs = $macroDefinition->bindArguments($args);

        // Créer un contexte de variables pour l'exécution de la macro
        $code .= "{$indent}\$__macroContext = [];\n";
        foreach ($boundArgs as $paramName => $argExpr) {
            // Si l'argument est une variable, la résoudre; sinon c'est une valeur littérale
            if ($this->isVariableExpression($argExpr)) {
                $code .= "{$indent}\$__macroContext['{$paramName}'] = \$__helpers['resolveVariable']('{$argExpr}', \$__variables);\n";
            } else {
                // Valeur littérale (string, number, etc.)
                $phpValue = $this->compileLiteralValue($argExpr);
                $code .= "{$indent}\$__macroContext['{$paramName}'] = {$phpValue};\n";
            }
        }

        // Compiler le corps de la macro avec le contexte
        $code .= "{$indent}\$__savedVariables = \$__variables;\n";
        $code .= "{$indent}\$__variables = array_merge(\$__variables, \$__macroContext);\n";
        $code .= "{$indent}// Macro body:\n";

        // Compiler les enfants du body de la macro
        // Déterminer le niveau d'indentation numérique pour compileNode
        $indentLevel = (int)(strlen($indent) / 4);
        foreach ($macroDefinition->body->children as $child) {
            $code .= $this->compileNode($child, $indentLevel);
        }

        $code .= "{$indent}\$__variables = \$__savedVariables;\n";

        return $code;
    }

    /**
     * Parse les arguments d'un appel de macro
     * 
     * Exemples:
     * - "name, value" => [0 => 'name', 1 => 'value']
     * - "'name', value" => [0 => "'name'", 1 => 'value']
     * - "name, value, type='text'" => [0 => 'name', 1 => 'value', 'type' => "'text'"]
     * 
     * Note: Les quotes sont PRÉSERVÉES pour permettre de distinguer variables et literals
     * 
     * @param string $argsString Arguments sous forme de string
     * @return array<int|string, string> Arguments parsés (quotes préservées)
     */
    private function parseMacroCallArguments(string $argsString): array
    {
        $argsString = trim($argsString);

        if ($argsString === '') {
            return [];
        }

        $args = [];
        $parts = $this->splitMacroArguments($argsString);

        foreach ($parts as $part) {
            $part = trim($part);

            // Argument nommé: name='value' ou name="value" ou name=value
            if (preg_match('/^(\w+)\s*=\s*(.+)$/', $part, $matches)) {
                $args[$matches[1]] = trim($matches[2]); // Garde les quotes si présentes
            }
            // Argument positionnel
            else {
                $args[] = $part; // Garde les quotes si présentes
            }
        }

        return $args;
    }

    /**
     * Sépare les arguments d'un appel de macro en tenant compte des quotes et parenthèses
     */
    private function splitMacroArguments(string $argsString): array
    {
        $parts = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $depth = 0; // Profondeur de parenthèses

        for ($i = 0; $i < strlen($argsString); $i++) {
            $char = $argsString[$i];

            if (($char === '"' || $char === "'") && (!$inQuotes || $char === $quoteChar)) {
                $inQuotes = !$inQuotes;
                $quoteChar = $inQuotes ? $char : null;
                $current .= $char;
            } elseif ($char === '(' && !$inQuotes) {
                $depth++;
                $current .= $char;
            } elseif ($char === ')' && !$inQuotes) {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * Vérifie si une expression est une variable (ex: "user.name" vs "'hello'")
     */
    private function isVariableExpression(string $expr): bool
    {
        // Si commence par quote, c'est un literal
        if (preg_match('/^["\']/', $expr)) {
            return false;
        }

        // Si c'est un nombre, c'est un literal
        if (is_numeric($expr)) {
            return false;
        }

        // Si c'est true/false/null, c'est un literal
        if (in_array($expr, ['true', 'false', 'null'], true)) {
            return false;
        }

        // Sinon c'est probablement une variable
        return true;
    }

    /**
     * Compile une valeur littérale en PHP
     */
    private function compileLiteralValue(string $value): string
    {
        // String déjà entre quotes (simple ou double)
        if (preg_match('/^(["\'])(.*)\\1$/s', $value, $matches)) {
            return "'" . addcslashes($matches[2], "'\\") . "'";
        }

        // Nombres
        if (is_numeric($value)) {
            return $value;
        }

        // Booleans
        if ($value === 'true') {
            return 'true';
        }
        if ($value === 'false') {
            return 'false';
        }

        // null
        if ($value === 'null') {
            return 'null';
        }

        // Par défaut, traiter comme string
        return "'" . addcslashes($value, "'\\") . "'";
    }
}
