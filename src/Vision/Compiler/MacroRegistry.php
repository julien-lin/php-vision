<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Compiler;

use JulienLinard\Vision\Parser\ASTNode;
use JulienLinard\Vision\Exception\VisionException;

/**
 * Registre des macros pour un template
 * 
 * Les macros sont des fonctions de templates réutilisables, inspirées de Twig.
 * 
 * Exemple :
 * {% macro input(name, value, type="text") %}
 *     <input type="{{ type }}" name="{{ name }}" value="{{ value }}">
 * {% endmacro %}
 * 
 * {{ input("email", user.email, "email") }}
 */
class MacroRegistry
{
    /**
     * Macros définies dans ce template
     * 
     * @var array<string, MacroDefinition>
     */
    private array $macros = [];

    /**
     * Macros importées depuis d'autres templates
     * Format: alias => ['template' => 'path/to/template', 'macros' => MacroRegistry]
     * 
     * @var array<string, array{template: string, macros: MacroRegistry}>
     */
    private array $imports = [];

    /**
     * Définit une macro
     * 
     * @param string $name Nom de la macro
     * @param array<string> $parameters Noms des paramètres
     * @param array<string, mixed> $defaults Valeurs par défaut des paramètres
     * @param ASTNode $body Corps de la macro (AST)
     */
    public function define(string $name, array $parameters, array $defaults, ASTNode $body): void
    {
        if (isset($this->macros[$name])) {
            throw new VisionException("Macro '{$name}' is already defined");
        }

        $this->macros[$name] = new MacroDefinition($name, $parameters, $defaults, $body);
    }

    /**
     * Vérifie si une macro est définie
     */
    public function has(string $name): bool
    {
        return isset($this->macros[$name]);
    }

    /**
     * Récupère une macro définie
     * 
     * @throws VisionException Si la macro n'existe pas
     */
    public function get(string $name): MacroDefinition
    {
        if (!isset($this->macros[$name])) {
            throw new VisionException("Undefined macro: {$name}");
        }

        return $this->macros[$name];
    }

    /**
     * Obtient toutes les macros définies
     * 
     * @return array<string, MacroDefinition>
     */
    public function all(): array
    {
        return $this->macros;
    }

    /**
     * Importe les macros d'un autre template avec un alias
     * 
     * @param string $alias Alias pour accéder aux macros (ex: "forms")
     * @param string $templatePath Chemin du template contenant les macros
     * @param MacroRegistry $macros Registre des macros du template importé
     */
    public function import(string $alias, string $templatePath, MacroRegistry $macros): void
    {
        if (isset($this->imports[$alias])) {
            throw new VisionException("Import alias '{$alias}' is already used");
        }

        $this->imports[$alias] = [
            'template' => $templatePath,
            'macros' => $macros
        ];
    }

    /**
     * Vérifie si un alias d'import existe
     */
    public function hasImport(string $alias): bool
    {
        return isset($this->imports[$alias]);
    }

    /**
     * Récupère une macro importée via un alias
     * 
     * @param string $alias Alias de l'import (ex: "forms")
     * @param string $macroName Nom de la macro (ex: "input")
     * @throws VisionException Si l'import ou la macro n'existe pas
     */
    public function getImported(string $alias, string $macroName): MacroDefinition
    {
        if (!isset($this->imports[$alias])) {
            throw new VisionException("Undefined import alias: {$alias}");
        }

        $importedRegistry = $this->imports[$alias]['macros'];

        if (!$importedRegistry->has($macroName)) {
            $template = $this->imports[$alias]['template'];
            throw new VisionException("Macro '{$macroName}' not found in imported template '{$template}'");
        }

        return $importedRegistry->get($macroName);
    }

    /**
     * Obtient tous les imports
     * 
     * @return array<string, array{template: string, macros: MacroRegistry}>
     */
    public function allImports(): array
    {
        return $this->imports;
    }
}

/**
 * Définition d'une macro
 */
class MacroDefinition
{
    /**
     * @param string $name Nom de la macro
     * @param array<string> $parameters Liste des paramètres
     * @param array<string, mixed> $defaults Valeurs par défaut
     * @param ASTNode $body Corps de la macro (AST)
     */
    public function __construct(
        public readonly string $name,
        public readonly array $parameters,
        public readonly array $defaults,
        public readonly ASTNode $body
    ) {}

    /**
     * Valide et prépare les arguments pour l'appel de la macro
     * 
     * @param array<int|string, mixed> $arguments Arguments passés à la macro
     * @return array<string, mixed> Arguments mappés aux paramètres
     * @throws VisionException Si des arguments requis manquent
     */
    public function bindArguments(array $arguments): array
    {
        $bound = [];
        $positionalArgs = [];
        $namedArgs = [];

        // Séparer les arguments positionnels et nommés
        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                $positionalArgs[] = $value;
            } else {
                $namedArgs[$key] = $value;
            }
        }

        // Mapper les arguments positionnels
        foreach ($positionalArgs as $index => $value) {
            if (!isset($this->parameters[$index])) {
                throw new VisionException("Too many arguments for macro '{$this->name}'");
            }
            $bound[$this->parameters[$index]] = $value;
        }

        // Ajouter les arguments nommés
        foreach ($namedArgs as $name => $value) {
            if (!in_array($name, $this->parameters, true)) {
                throw new VisionException("Unknown parameter '{$name}' for macro '{$this->name}'");
            }
            if (isset($bound[$name])) {
                throw new VisionException("Parameter '{$name}' already set for macro '{$this->name}'");
            }
            $bound[$name] = $value;
        }

        // Ajouter les valeurs par défaut pour les paramètres manquants
        foreach ($this->parameters as $param) {
            if (!isset($bound[$param])) {
                if (array_key_exists($param, $this->defaults)) {
                    $bound[$param] = $this->defaults[$param];
                } else {
                    throw new VisionException("Missing required parameter '{$param}' for macro '{$this->name}'");
                }
            }
        }

        return $bound;
    }
}
