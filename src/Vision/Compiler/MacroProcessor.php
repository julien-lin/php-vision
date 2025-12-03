<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Compiler;

use JulienLinard\Vision\Parser\ASTNode;
use JulienLinard\Vision\Parser\NodeType;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Exception\VisionException;

/**
 * Processeur de macros
 * 
 * Extrait les macros d'un AST et gère les imports de macros depuis d'autres templates.
 */
class MacroProcessor
{
    /**
     * @param callable $templateLoader Callback pour charger le contenu d'un template
     * @param TemplateParser $parser Parser pour analyser les templates importés
     */
    public function __construct(
        private $templateLoader,
        private TemplateParser $parser
    ) {}

    /**
     * Extrait toutes les macros définies dans un AST
     * 
     * @param ASTNode $ast AST du template
     * @return MacroRegistry Registre contenant les macros
     */
    public function extractMacros(ASTNode $ast): MacroRegistry
    {
        $registry = new MacroRegistry();

        foreach ($ast->children as $child) {
            if ($child->type === NodeType::MACRO) {
                $this->processMacroDefinition($child, $registry);
            }
        }

        return $registry;
    }

    /**
     * Traite une définition de macro
     * 
     * @param ASTNode $macroNode Nœud MACRO
     * @param MacroRegistry $registry Registre où ajouter la macro
     */
    private function processMacroDefinition(ASTNode $macroNode, MacroRegistry $registry): void
    {
        // metadata[1][0] = nom de la macro
        // metadata[2][0] = signature des paramètres (ex: "name, value, type='text'")

        if (!isset($macroNode->metadata[1][0])) {
            throw new VisionException('Invalid macro definition: missing name');
        }

        $macroName = $macroNode->metadata[1][0];
        $paramSignature = $macroNode->metadata[2][0] ?? '';

        // Parser la signature des paramètres
        [$parameters, $defaults] = $this->parseParameterSignature($paramSignature);

        // Le body de la macro est composé de tous les enfants du nœud MACRO
        $body = $macroNode;

        $registry->define($macroName, $parameters, $defaults, $body);
    }

    /**
     * Parse la signature des paramètres d'une macro
     * 
     * Exemples:
     * - "name, value" => [['name', 'value'], []]
     * - "name, value, type='text'" => [['name', 'value', 'type'], ['type' => 'text']]
     * - "name, type='text', required=true" => [['name', 'type', 'required'], ['type' => 'text', 'required' => true]]
     * 
     * @param string $signature Signature des paramètres
     * @return array{0: array<string>, 1: array<string, mixed>} [paramètres, défauts]
     */
    private function parseParameterSignature(string $signature): array
    {
        $signature = trim($signature);

        if ($signature === '') {
            return [[], []];
        }

        $parameters = [];
        $defaults = [];

        // Découper par virgules (attention aux valeurs par défaut qui peuvent contenir des virgules)
        $parts = $this->splitParameters($signature);

        foreach ($parts as $part) {
            $part = trim($part);

            // Paramètre avec valeur par défaut : name="value" ou name='value' ou name=123
            if (preg_match('/^(\w+)\s*=\s*(.+)$/', $part, $matches)) {
                $paramName = $matches[1];
                $defaultValue = $this->parseDefaultValue($matches[2]);

                $parameters[] = $paramName;
                $defaults[$paramName] = $defaultValue;
            }
            // Paramètre simple : name
            elseif (preg_match('/^(\w+)$/', $part, $matches)) {
                $parameters[] = $matches[1];
            } else {
                throw new VisionException("Invalid macro parameter: {$part}");
            }
        }

        return [$parameters, $defaults];
    }

    /**
     * Découpe une signature de paramètres en tenant compte des quotes
     */
    private function splitParameters(string $signature): array
    {
        $parts = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;

        for ($i = 0; $i < strlen($signature); $i++) {
            $char = $signature[$i];

            if (($char === '"' || $char === "'") && (!$inQuotes || $char === $quoteChar)) {
                $inQuotes = !$inQuotes;
                $quoteChar = $inQuotes ? $char : null;
                $current .= $char;
            } elseif ($char === ',' && !$inQuotes) {
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
     * Parse une valeur par défaut
     * 
     * Supporte:
     * - Strings: "value" ou 'value'
     * - Numbers: 123, 45.67
     * - Booleans: true, false
     * - null
     */
    private function parseDefaultValue(string $value): mixed
    {
        $value = trim($value);

        // String avec quotes: préserver en littéral string
        if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
            return "'" . addcslashes($matches[2], "'\\") . "'";
        }

        // Boolean
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        // null
        if ($value === 'null') {
            return null;
        }

        // Number
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        // String sans quotes: traiter comme littéral string
        return "'" . addcslashes($value, "'\\") . "'";
    }

    /**
     * Traite les imports de macros dans un AST
     * 
     * @param ASTNode $ast AST du template
     * @param MacroRegistry $registry Registre où ajouter les imports
     * @param string $currentTemplate Nom du template actuel (pour résoudre les chemins)
     */
    public function processImports(ASTNode $ast, MacroRegistry $registry, string $currentTemplate): void
    {
        foreach ($ast->children as $child) {
            if ($child->type === NodeType::IMPORT) {
                $this->processImport($child, $registry, $currentTemplate);
            }
        }
    }

    /**
     * Traite un import de macros
     * 
     * @param ASTNode $importNode Nœud IMPORT
     * @param MacroRegistry $registry Registre où ajouter l'import
     * @param string $currentTemplate Template actuel
     */
    private function processImport(ASTNode $importNode, MacroRegistry $registry, string $currentTemplate): void
    {
        // metadata[1][0] = chemin du template à importer
        // metadata[2][0] = alias

        if (!isset($importNode->metadata[1][0]) || !isset($importNode->metadata[2][0])) {
            throw new VisionException('Invalid import directive');
        }

        $templatePath = $importNode->metadata[1][0];
        $alias = $importNode->metadata[2][0];

        // Charger et parser le template importé
        $templateContent = ($this->templateLoader)($templatePath);
        $parsed = $this->parser->parse($templateContent);

        // Extraire les macros du template importé
        $importedMacros = $this->extractMacros($parsed->ast);

        // Enregistrer l'import
        $registry->import($alias, $templatePath, $importedMacros);
    }

    /**
     * Supprime les définitions de macros et imports de l'AST
     * 
     * Les macros sont extraites et stockées dans le registry,
     * elles ne doivent pas être compilées dans le template.
     * 
     * @param ASTNode $ast AST à nettoyer
     * @return ASTNode AST sans macros ni imports
     */
    public function removeMacroDefinitions(ASTNode $ast): ASTNode
    {
        $cleanedChildren = [];

        foreach ($ast->children as $child) {
            // Filtrer les macros et imports
            if ($child->type !== NodeType::MACRO && $child->type !== NodeType::IMPORT) {
                $cleanedChildren[] = $child;
            }
        }

        // Créer un nouvel AST avec les enfants filtrés
        $cleanedAst = new ASTNode($ast->type, $ast->value);
        foreach ($cleanedChildren as $child) {
            $cleanedAst->addChild($child);
        }

        return $cleanedAst;
    }
}
