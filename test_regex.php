<?php

// Test isVariableExpression logic
function isVariableExpression(string $expr): bool
{
    // Si commence par quote, c'est un literal
    if (preg_match('/^["\']/', $expr)) {
        echo "Matches quote pattern: YES\n";
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

function compileLiteralValue(string $value): string
{
    // String déjà entre quotes (simple ou double)
    if (preg_match('/^(["\'])(.*)\\1$/s', $value, $matches)) {
        echo "Matched quotes pattern. Captured: [{$matches[2]}]\n";
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

// Test avec 'World'
$input = "'World'";
echo "Testing with: {$input}\n";
echo "Is variable? " . (isVariableExpression($input) ? 'YES' : 'NO') . "\n";
echo "Compiled value: " . compileLiteralValue($input) . "\n";
