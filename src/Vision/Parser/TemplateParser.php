<?php

namespace JulienLinard\Vision\Parser;

use JulienLinard\Vision\Exception\VisionException;

/**
 * Parser pour les templates Vision
 * 
 * Responsabilité: Analyser et parser la syntaxe du template
 * Sépare la logique de parsing de la logique de rendu
 */
class TemplateParser
{
    // Patterns de détection optimisés (réutilisés depuis Vision.php)
    private const PATTERN_COMMENT = '/\{#.*?#\}/s';
    private const PATTERN_FOR = '/\{%\s*for\s+(\w{1,50})\s+in\s+([^\s%]{1,200})(?:\s+if\s+([^%]{1,200}))?\s*%\}/';
    private const PATTERN_ENDFOR = '/\{%\s*endfor\s*%\}/';
    private const PATTERN_IF = '/\{%\s*if\s+([^%]{1,200})\s*%\}/';
    private const PATTERN_ELSEIF = '/\{%\s*elseif\s+([^%]{1,200})\s*%\}/';
    private const PATTERN_ELSE = '/\{%\s*else\s*%\}/';
    private const PATTERN_ENDIF = '/\{%\s*endif\s*%\}/';
    // Autoriser plus de caractères (dont '@', '/', '?', '#', '&', '=', opérateurs) dans les expressions, notamment à l'intérieur des quotes
    private const PATTERN_VARIABLE = '/\{\{\s*([a-zA-Z0-9_][^|}]{0,300})\s*(?:\|\s*([a-zA-Z_][^}]{0,200}))?\s*\}\}/s';

    // Template Inheritance patterns
    private const PATTERN_EXTENDS = '/\{%\s*extends\s+["\']([^"\']+)["\']\s*%\}/';
    private const PATTERN_BLOCK = '/\{%\s*block\s+(\w+)\s*%\}/';
    private const PATTERN_ENDBLOCK = '/\{%\s*endblock\s*%\}/';
    private const PATTERN_PARENT = '/\{\{\s*parent\(\s*\)\s*\}\}/';

    // Macro patterns
    private const PATTERN_MACRO = '/\{%\s*macro\s+(\w+)\s*\(([^)]*)\)\s*%\}/';
    private const PATTERN_ENDMACRO = '/\{%\s*endmacro\s*%\}/';
    private const PATTERN_IMPORT = '/\{%\s*import\s+["\']([^"\']+)["\']\s+as\s+(\w+)\s*%\}/';
    // Pattern pour appel de macro: {{ macroName(...) }} ou {{ alias.macroName(...) }}
    private const PATTERN_MACRO_CALL = '/\{\{\s*(\w+(?:\.\w+)?)\s*\(([^}]*)\)\s*\}\}/';

    /**
     * Pool d'objets ASTNode (préparé pour future optimisation)
     * 
     * Note: Actuellement limité car ASTNode utilise des propriétés readonly.
     * Le pool nettoie les références pour aider le GC.
     */
    private ?ASTNodePool $nodePool = null;

    /**
     * Obtient ou crée le pool de nœuds
     */
    private function getNodePool(): ASTNodePool
    {
        if ($this->nodePool === null) {
            $this->nodePool = new ASTNodePool();
        }
        return $this->nodePool;
    }

    /**
     * Parse un template en une structure de données analysable
     * 
     * @param string $content Contenu brut du template
     * @return ParsedTemplate Structure parsée du template
     * @throws VisionException
     */
    public function parse(string $content): ParsedTemplate
    {
        // 1. Supprimer les commentaires
        $content = $this->removeComments($content);

        // 2. Parser la structure en tokens
        $tokens = $this->tokenize($content);

        // 3. Construire l'arbre syntaxique
        $ast = $this->buildAST($tokens);

        return new ParsedTemplate($content, $tokens, $ast);
    }

    /**
     * Supprime les commentaires du template
     */
    private function removeComments(string $content): string
    {
        $result = preg_replace(self::PATTERN_COMMENT, '', $content);
        if ($result === null) {
            throw new VisionException('Erreur lors du traitement des commentaires');
        }
        return $result;
    }

    /**
     * Tokenize le contenu en unités lexicales
     * 
     * @return array<int, Token>
     */
    private function tokenize(string $content): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            // Chercher le prochain tag
            $nextTag = $this->findNextTag($content, $offset);

            if ($nextTag === null) {
                // Plus de tags, ajouter le reste comme texte brut
                if ($offset < $length) {
                    $tokens[] = new Token(
                        TokenType::TEXT,
                        substr($content, $offset),
                        $offset
                    );
                }
                break;
            }

            // Ajouter le texte avant le tag
            if ($nextTag['position'] > $offset) {
                $tokens[] = new Token(
                    TokenType::TEXT,
                    substr($content, $offset, $nextTag['position'] - $offset),
                    $offset
                );
            }

            // Ajouter le token du tag
            $tokens[] = $nextTag['token'];
            $offset = $nextTag['position'] + strlen($nextTag['token']->value);
        }

        return $tokens;
    }

    /**
     * Trouve le prochain tag dans le contenu
     * 
     * @return array{position: int, token: Token}|null
     */
    private function findNextTag(string $content, int $offset): ?array
    {
        // Liste des patterns à chercher
        $patterns = [
            ['type' => TokenType::EXTENDS, 'pattern' => self::PATTERN_EXTENDS],
            ['type' => TokenType::BLOCK_START, 'pattern' => self::PATTERN_BLOCK],
            ['type' => TokenType::BLOCK_END, 'pattern' => self::PATTERN_ENDBLOCK],
            ['type' => TokenType::PARENT, 'pattern' => self::PATTERN_PARENT],
            ['type' => TokenType::MACRO_START, 'pattern' => self::PATTERN_MACRO],
            ['type' => TokenType::MACRO_END, 'pattern' => self::PATTERN_ENDMACRO],
            ['type' => TokenType::IMPORT, 'pattern' => self::PATTERN_IMPORT],
            ['type' => TokenType::FOR_START, 'pattern' => self::PATTERN_FOR],
            ['type' => TokenType::FOR_END, 'pattern' => self::PATTERN_ENDFOR],
            ['type' => TokenType::IF_START, 'pattern' => self::PATTERN_IF],
            ['type' => TokenType::ELSEIF, 'pattern' => self::PATTERN_ELSEIF],
            ['type' => TokenType::ELSE, 'pattern' => self::PATTERN_ELSE],
            ['type' => TokenType::IF_END, 'pattern' => self::PATTERN_ENDIF],
            ['type' => TokenType::VARIABLE, 'pattern' => self::PATTERN_VARIABLE],
        ];

        $nearestPosition = PHP_INT_MAX;
        $nearestToken = null;

        foreach ($patterns as $patternDef) {
            $type = $patternDef['type'];
            $pattern = $patternDef['pattern'];

            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                $position = $matches[0][1];
                if ($position < $nearestPosition) {
                    $nearestPosition = $position;
                    $nearestToken = new Token($type, $matches[0][0], $position, $matches);
                }
            }
        }

        if ($nearestToken === null) {
            return null;
        }

        return [
            'position' => $nearestPosition,
            'token' => $nearestToken
        ];
    }

    /**
     * Construit l'arbre syntaxique abstrait (AST) depuis les tokens
     * 
     * @param array<int, Token> $tokens
     * @return ASTNode
     */
    private function buildAST(array $tokens): ASTNode
    {
        $pool = $this->getNodePool();

        $root = $pool->acquire(NodeType::ROOT);
        $stack = [$root];
        $currentParent = $root;

        foreach ($tokens as $token) {
            switch ($token->type) {
                case TokenType::TEXT:
                    $node = $pool->acquire(NodeType::TEXT, $token->value);
                    $currentParent->addChild($node);
                    break;

                case TokenType::VARIABLE:
                    $node = $pool->acquire(NodeType::VARIABLE, $token->value);
                    $node->metadata = $token->matches;
                    $currentParent->addChild($node);
                    break;

                case TokenType::FOR_START:
                    $node = $pool->acquire(NodeType::FOR_LOOP, $token->value);
                    $node->metadata = $token->matches;
                    $currentParent->addChild($node);
                    $stack[] = $node;
                    $currentParent = $node;
                    break;

                case TokenType::FOR_END:
                    array_pop($stack);
                    $currentParent = end($stack);
                    break;

                case TokenType::IF_START:
                    $node = $pool->acquire(NodeType::IF_CONDITION, $token->value);
                    $node->metadata = $token->matches;
                    $currentParent->addChild($node);
                    $stack[] = $node;
                    $currentParent = $node;
                    break;

                case TokenType::ELSEIF:
                    // Remonter au parent IF
                    array_pop($stack);
                    $ifNode = end($stack);
                    $node = $pool->acquire(NodeType::ELSEIF_CONDITION, $token->value);
                    $node->metadata = $token->matches;
                    $ifNode->addChild($node);
                    $stack[] = $node;
                    $currentParent = $node;
                    break;

                case TokenType::ELSE:
                    // Remonter au parent IF
                    array_pop($stack);
                    $ifNode = end($stack);
                    $node = $pool->acquire(NodeType::ELSE_CONDITION, $token->value);
                    $ifNode->addChild($node);
                    $stack[] = $node;
                    $currentParent = $node;
                    break;

                case TokenType::IF_END:
                    array_pop($stack);
                    $currentParent = end($stack);
                    break;

                case TokenType::EXTENDS:
                    $node = $pool->acquire(NodeType::EXTENDS, $token->value);
                    $node->metadata = $token->matches;
                    $currentParent->addChild($node);
                    break;

                case TokenType::BLOCK_START:
                    $node = $pool->acquire(NodeType::BLOCK, $token->value);
                    $node->metadata = $token->matches;
                    $currentParent->addChild($node);
                    $stack[] = $node;
                    $currentParent = $node;
                    break;

                case TokenType::BLOCK_END:
                    array_pop($stack);
                    $currentParent = end($stack);
                    break;

                case TokenType::PARENT:
                    $node = $pool->acquire(NodeType::PARENT, $token->value);
                    $currentParent->addChild($node);
                    break;

                case TokenType::MACRO_START:
                    $node = $pool->acquire(NodeType::MACRO, $token->value);
                    $node->metadata = $token->matches;
                    $currentParent->addChild($node);
                    $stack[] = $node;
                    $currentParent = $node;
                    break;

                case TokenType::MACRO_END:
                    array_pop($stack);
                    $currentParent = end($stack);
                    break;

                case TokenType::IMPORT:
                    $node = $pool->acquire(NodeType::IMPORT, $token->value);
                    $node->metadata = $token->matches;
                    $currentParent->addChild($node);
                    break;
            }
        }

        return $root;
    }
}
