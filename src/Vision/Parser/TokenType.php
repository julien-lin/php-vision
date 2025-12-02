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
}
