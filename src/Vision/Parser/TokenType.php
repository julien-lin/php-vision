<?php

namespace JulienLinard\Vision\Parser;

/**
 * Types de tokens dans un template Vision
 */
enum TokenType: string
{
    case TEXT = 'text';
    case VARIABLE = 'variable';
    case FOR_START = 'for_start';
    case FOR_END = 'for_end';
    case IF_START = 'if_start';
    case ELSEIF = 'elseif';
    case ELSE = 'else';
    case IF_END = 'if_end';
    case COMMENT = 'comment';
    case EXTENDS = 'extends';
    case BLOCK_START = 'block_start';
    case BLOCK_END = 'block_end';
    case PARENT = 'parent';
    case MACRO_START = 'macro_start';
    case MACRO_END = 'macro_end';
    case IMPORT = 'import';
}
