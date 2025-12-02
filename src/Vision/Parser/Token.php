<?php

namespace JulienLinard\Vision\Parser;

/**
 * Représente un token lexical dans un template
 */
class Token
{
    /**
     * @param TokenType $type Type du token
     * @param string $value Valeur brute du token
     * @param int $position Position dans le contenu original
     * @param array<int|string, mixed> $matches Matches regex associées
     */
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $position = 0,
        public readonly array $matches = []
    ) {}
}
