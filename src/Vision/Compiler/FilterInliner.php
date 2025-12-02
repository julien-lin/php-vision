<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Compiler;

/**
 * Filter Inliner - Compile simple filters directly to native PHP code
 * 
 * Optimizes filter application by eliminating FilterManager overhead
 * and generating direct PHP function calls at compile time.
 * 
 * Example: {{ name|upper }} → strtoupper($value)
 * vs FilterManager::apply('upper', $value)
 */
class FilterInliner
{
    /**
     * Mapping of filter names to inline PHP code patterns
     * %s is replaced with the variable expression
     * 
     * @var array<string, string>
     */
    private array $inlineMap = [
        'upper' => 'strtoupper(%s)',
        'lower' => 'strtolower(%s)',
        'trim' => 'trim(%s)',
        'escape' => 'htmlspecialchars((string)%s, ENT_QUOTES | ENT_HTML5, \'UTF-8\')',
        'length' => '(is_array(%s) || %s instanceof \\Countable ? count(%s) : strlen((string)%s))',
        'json' => 'json_encode(%s, JSON_THROW_ON_ERROR)',
    ];

    /**
     * Filters that require parameters and cannot be simply inlined
     * 
     * @var array<string>
     */
    private array $nonInlineable = [
        'default',      // Needs default value parameter
        'date',         // Needs format parameter
        'number',       // Needs precision/decimals parameters
    ];

    /**
     * Checks if a filter can be inlined
     *
     * @param string $filterName Name of the filter
     * @return bool True if filter can be inlined
     */
    public function canInline(string $filterName): bool
    {
        // Parse filter name (may include parameters like "default:value")
        $baseName = explode(':', $filterName)[0];
        
        return isset($this->inlineMap[$baseName]) && !in_array($baseName, $this->nonInlineable, true);
    }

    /**
     * Generates inline PHP code for a filter
     *
     * @param string $filterName Name of the filter
     * @param string $valueExpr PHP expression for the value to filter
     * @return string PHP code implementing the filter
     */
    public function inline(string $filterName, string $valueExpr): string
    {
        $baseName = explode(':', $filterName)[0];
        
        if (!$this->canInline($baseName)) {
            throw new \InvalidArgumentException("Filter '$filterName' cannot be inlined");
        }

        $pattern = $this->inlineMap[$baseName];
        
        // Handle filters that need the value multiple times (like length)
        $occurrences = substr_count($pattern, '%s');
        if ($occurrences > 1) {
            // Use array_fill to repeat the value expression
            return sprintf($pattern, ...array_fill(0, $occurrences, $valueExpr));
        }
        
        return sprintf($pattern, $valueExpr);
    }

    /**
     * Generates PHP code for a filter chain
     * Inlines what's possible, falls back to FilterManager for complex filters
     *
     * @param string $valueVar Variable name containing the value (e.g., '$__value')
     * @param array<string> $filters Array of filter names
     * @param string $indent Indentation string
     * @return string PHP code for the filter chain
     */
    public function compileFilterChain(string $valueVar, array $filters, string $indent = ''): string
    {
        $code = '';
        $currentExpr = $valueVar;
        
        foreach ($filters as $filter) {
            $filter = trim($filter);
            if ($filter === '') {
                continue;
            }
            
            if ($this->canInline($filter)) {
                // Inline: generate direct PHP code
                $inlined = $this->inline($filter, $currentExpr);
                $code .= "{$indent}{$valueVar} = {$inlined}; // Inlined: {$filter}\n";
                $currentExpr = $valueVar;
            } else {
                // Fallback: use FilterManager
                $code .= "{$indent}{$valueVar} = \$__helpers['applyFilter']('{$filter}', {$currentExpr});\n";
                $currentExpr = $valueVar;
            }
        }
        
        return $code;
    }

    /**
     * Registers a custom inline filter mapping
     *
     * @param string $filterName Name of the filter
     * @param string $phpPattern PHP code pattern with %s placeholder
     * @return void
     */
    public function registerInlineFilter(string $filterName, string $phpPattern): void
    {
        $this->inlineMap[$filterName] = $phpPattern;
    }

    /**
     * Gets all inlineable filter names
     *
     * @return array<string>
     */
    public function getInlineableFilters(): array
    {
        return array_keys($this->inlineMap);
    }

    /**
     * Statistics: percentage of filters that can be inlined
     *
     * @param array<string> $filters List of filter names used in templates
     * @return array{inlined: int, total: int, percentage: float}
     */
    public function analyzeInlineablility(array $filters): array
    {
        $total = count($filters);
        $inlined = 0;
        
        foreach ($filters as $filter) {
            if ($this->canInline($filter)) {
                $inlined++;
            }
        }
        
        $percentage = $total > 0 ? ($inlined / $total) * 100 : 0;
        
        return [
            'inlined' => $inlined,
            'total' => $total,
            'percentage' => round($percentage, 2)
        ];
    }
}
