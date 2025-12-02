<?php

namespace JulienLinard\Vision\Parser;

/**
 * Représente un template parsé avec ses tokens et son AST
 */
class ParsedTemplate
{
    /**
     * @param string $content Contenu nettoyé (sans commentaires)
     * @param array<int, Token> $tokens Liste des tokens
     * @param ASTNode $ast Arbre syntaxique abstrait
     */
    public function __construct(
        public readonly string $content,
        public readonly array $tokens,
        public readonly ASTNode $ast
    ) {}
}
