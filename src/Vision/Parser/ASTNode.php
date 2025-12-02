<?php

namespace JulienLinard\Vision\Parser;

/**
 * Nœud de l'arbre syntaxique abstrait (AST)
 */
class ASTNode
{
    /** @var array<int, ASTNode> */
    public array $children = [];
    
    /** @var array<string, mixed> */
    public array $metadata = [];

    /**
     * @param NodeType $type Type du nœud
     * @param string $value Valeur associée au nœud
     */
    public function __construct(
        public readonly NodeType $type,
        public readonly string $value = ''
    ) {}

    /**
     * Ajoute un nœud enfant
     */
    public function addChild(ASTNode $child): void
    {
        $this->children[] = $child;
    }

    /**
     * Retourne tous les enfants
     * @return array<int, ASTNode>
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
