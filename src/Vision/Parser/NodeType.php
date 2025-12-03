<?php

namespace JulienLinard\Vision\Parser;

/**
 * Types de nœuds dans l'AST
 */
enum NodeType: string
{
    case ROOT = 'root';
    case TEXT = 'text';
    case VARIABLE = 'variable';
    case FOR_LOOP = 'for_loop';
    case IF_CONDITION = 'if_condition';
    case ELSEIF_CONDITION = 'elseif_condition';
    case ELSE_CONDITION = 'else_condition';
    case EXTENDS = 'extends';
    case BLOCK = 'block';
    case PARENT = 'parent';
    case MACRO = 'macro';
    case IMPORT = 'import';
    case MACRO_CALL = 'macro_call';
}
