<?php

namespace JulienLinard\Vision\Runtime;

class ControlStructureProcessor
{
    private const REGEX_FOR_TAG = '/{%\s*for\s+/';
    private const REGEX_ENDFOR_TAG = '/{%\s*endfor\s*%}/';
    private const REGEX_IF_TAG = '/{%\s*if\s+/';
    private const REGEX_ENDIF_TAG = '/{%\s*endif\s*%}/';
    private const REGEX_ELSE_TAG = '/{%\s*else\s*%}/';

    public function process(string $content, array $variables, int $depth, callable $render, callable $evaluateCondition, callable $resolve): string
    {
        $maxIterations = 100;
        $iteration = 0;
        while ($iteration < $maxIterations) {
            $original = $content;
            $content = $this->processForLoops($content, $variables, $depth, $render, $resolve);
            $content = $this->processIfConditions($content, $variables, $depth, $render, $evaluateCondition);
            if ($content === $original) {
                break;
            }
            $iteration++;
        }
        return $content;
    }

    private function processForLoops(string $content, array $variables, int $depth, callable $render, callable $resolve): string
    {
        // Match: for var in (array | range | number..number | number..number..step)
        if (!preg_match('/{%\s*for\s+(\w+(?:\s*,\s*\w+)*)\s+in\s+([\w\-]+(?:\.\w+)*|(?:\-?\d+|\w+)\.\.(?:\-?\d+|\w+)(?:\.\.(?:\-?\d+|\w+))?)\s*(?:if\s+(.+?))?\s*%}/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $startPos = $matches[0][1];
        $startLen = strlen($matches[0][0]);
        $itemVarStr = $matches[1][0];
        $arrayVar = $matches[2][0];
        $condition = isset($matches[3][0]) ? trim($matches[3][0]) : null;

        // Parse multiple loop variables (for key, value in items)
        $itemVars = array_map('trim', explode(',', $itemVarStr));

        $pos = $startPos + $startLen;
        $level = 1;
        $loopContentParts = [];
        $elseContentParts = [];
        $inElse = false;

        while ($pos < strlen($content) && $level > 0) {
            $nextFor = strpos($content, '{%', $pos);
            if ($nextFor === false) {
                break;
            }
            $endTag = strpos($content, '%}', $nextFor);
            if ($endTag === false) {
                break;
            }
            $tag = substr($content, $nextFor, $endTag - $nextFor + 2);

            if (preg_match(self::REGEX_FOR_TAG, $tag)) {
                $level++;
                if ($inElse) {
                    $elseContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                } else {
                    $loopContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                }
                $pos = $endTag + 2;
            } elseif (preg_match(self::REGEX_ENDFOR_TAG, $tag)) {
                $level--;
                if ($level === 0) {
                    if ($inElse) {
                        $elseContentParts[] = substr($content, $pos, $nextFor - $pos);
                    } else {
                        $loopContentParts[] = substr($content, $pos, $nextFor - $pos);
                    }
                    $pos = $endTag + 2;
                    break;
                } else {
                    if ($inElse) {
                        $elseContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                    } else {
                        $loopContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                    }
                    $pos = $endTag + 2;
                }
            } elseif (preg_match(self::REGEX_ELSE_TAG, $tag) && $level === 1 && !$inElse) {
                // else for the for loop (not nested if)
                $loopContentParts[] = substr($content, $pos, $nextFor - $pos);
                $inElse = true;
                $pos = $endTag + 2;
            } else {
                if ($inElse) {
                    $elseContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                } else {
                    $loopContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                }
                $pos = $endTag + 2;
            }
        }

        if ($level !== 0) {
            return $content;
        }

        $loopContent = implode('', $loopContentParts);
        $elseContent = implode('', $elseContentParts);

        // Check if arrayVar is a range (e.g., 1..5, 0..10..2, start..end, -2..2)
        if (preg_match('/^(?:\-?\d+|\w+)\.\.(?:\-?\d+|\w+)(?:\.\.(?:\-?\d+|\w+))?$/', $arrayVar)) {
            $arrayValue = $this->parseRange($arrayVar, $variables);
        } else {
            $arrayValue = $resolve($variables, $arrayVar);
        }
        
        $result = '';
        $hasItems = false;

        if (is_array($arrayValue) || $arrayValue instanceof \Traversable) {
            if ($arrayValue instanceof \Traversable && !is_array($arrayValue)) {
                $arrayValue = iterator_to_array($arrayValue, true);
            }

            $arrayCount = count($arrayValue);
            $index = 0;

            foreach ($arrayValue as $key => $item) {
                // Handle for...if conditions
                if ($condition !== null) {
                    // Evaluate condition for this item
                    $loopVars = array_merge($variables, [$itemVars[0] => $item]);
                    if (count($itemVars) > 1) {
                        $loopVars[$itemVars[1]] = $key;
                    }
                    // Evaluate the condition with loopVars context
                    if (!$this->evaluateConditionSimple($condition, $loopVars)) {
                        continue;
                    }
                }

                $hasItems = true;
                $loopVariables = array_merge($variables, [
                    'loop' => [
                        'index' => $index + 1,
                        'index0' => $index,
                        'first' => $index === 0,
                        'last' => $index === $arrayCount - 1,
                        'length' => $arrayCount,
                    ],
                ]);

                // Handle single var (item) or two vars (key, value)
                if (count($itemVars) > 1) {
                    // Two vars: key, value
                    $loopVariables[$itemVars[0]] = $key;
                    $loopVariables[$itemVars[1]] = $item;
                } else {
                    // Single var: item
                    $loopVariables[$itemVars[0]] = $item;
                }

                $result .= $render($loopContent, $loopVariables, $depth + 1);
                $index++;
            }
        }

        // If no items and has else block, render else content
        if (!$hasItems && !empty($elseContent)) {
            $result = $render($elseContent, $variables, $depth + 1);
        }

        return substr($content, 0, $startPos) . $result . substr($content, $pos);
    }

    private function evaluateConditionSimple(string $condition, array $variables): bool
    {
        // Simple condition evaluation (for basic for...if support)
        // This is a simplified version that handles basic comparisons
        if (preg_match('/(\w+)\s*(>|<|>=|<=|==|!=)\s*(\d+)/', $condition, $m)) {
            $var = $m[1];
            $op = $m[2];
            $val = (int)$m[3];
            $varVal = $variables[$var] ?? null;

            if ($varVal === null) {
                return false;
            }

            return match ($op) {
                '>' => $varVal > $val,
                '<' => $varVal < $val,
                '>=' => $varVal >= $val,
                '<=' => $varVal <= $val,
                '==' => $varVal == $val,
                '!=' => $varVal != $val,
                default => false,
            };
        }
        return true;
    }

    private function parseRange(string $rangeStr, array $variables): array
    {
        // Parse range strings like "1..5", "0..10..2", "start..end", "-2..2"
        $parts = explode('..', $rangeStr);
        
        if (count($parts) < 2 || count($parts) > 3) {
            return [];
        }

        // Get start value
        $start = $this->resolveRangePart($parts[0], $variables);
        if ($start === null) {
            return [];
        }

        // Get end value
        $end = $this->resolveRangePart($parts[1], $variables);
        if ($end === null) {
            return [];
        }

        // Get step if provided
        $step = 1;
        if (count($parts) === 3) {
            $step = $this->resolveRangePart($parts[2], $variables);
            if ($step === null || $step === 0) {
                return [];
            }
        }

        // Create range array
        if ($step > 0 && $start <= $end) {
            return range($start, $end, $step);
        } elseif ($step < 0 && $start >= $end) {
            return range($start, $end, $step);
        } elseif ($step === 1 && $start <= $end) {
            return range($start, $end);
        }

        return [];
    }

    private function resolveRangePart(string $part, array $variables): ?int
    {
        // Try to parse as integer first
        if (is_numeric($part)) {
            return (int)$part;
        }

        // Try to resolve as variable
        if (isset($variables[$part]) && is_numeric($variables[$part])) {
            return (int)$variables[$part];
        }

        return null;
    }

    private function processIfConditions(string $content, array $variables, int $depth, callable $render, callable $evaluateCondition): string
    {
        if (!preg_match('/{%\s*if\s+(.+?)\s*%}/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $startPos = $matches[0][1];
        $startLen = strlen($matches[0][0]);
        $condition = trim($matches[1][0]);

        $pos = $startPos + $startLen;
        $level = 1;
        $trueContentParts = [];
        $falseContentParts = [];
        $inElse = false;

        while ($pos < strlen($content) && $level > 0) {
            $nextTag = strpos($content, '{%', $pos);
            if ($nextTag === false) {
                break;
            }
            $endBracket = strpos($content, '%}', $nextTag);
            if ($endBracket === false) {
                break;
            }
            $tag = substr($content, $nextTag, $endBracket - $nextTag + 2);

            if (preg_match(self::REGEX_IF_TAG, $tag)) {
                $level++;
                if ($inElse) {
                    $falseContentParts[] = substr($content, $pos, $nextTag - $pos + strlen($tag));
                } else {
                    $trueContentParts[] = substr($content, $pos, $nextTag - $pos + strlen($tag));
                }
                $pos = $endBracket + 2;
            } elseif (preg_match(self::REGEX_ENDIF_TAG, $tag)) {
                $level--;
                if ($level === 0) {
                    if ($inElse) {
                        $falseContentParts[] = substr($content, $pos, $nextTag - $pos);
                    } else {
                        $trueContentParts[] = substr($content, $pos, $nextTag - $pos);
                    }
                    $pos = $endBracket + 2;
                    break;
                } else {
                    if ($inElse) {
                        $falseContentParts[] = substr($content, $pos, $nextTag - $pos + strlen($tag));
                    } else {
                        $trueContentParts[] = substr($content, $pos, $nextTag - $pos + strlen($tag));
                    }
                    $pos = $endBracket + 2;
                }
            } elseif (preg_match(self::REGEX_ELSE_TAG, $tag) && $level === 1) {
                $trueContentParts[] = substr($content, $pos, $nextTag - $pos);
                $inElse = true;
                $pos = $endBracket + 2;
            } else {
                if ($inElse) {
                    $falseContentParts[] = substr($content, $pos, $nextTag - $pos + strlen($tag));
                } else {
                    $trueContentParts[] = substr($content, $pos, $nextTag - $pos + strlen($tag));
                }
                $pos = $endBracket + 2;
            }
        }

        if ($level !== 0) {
            return $content;
        }

        $trueContent = implode('', $trueContentParts);
        $falseContent = implode('', $falseContentParts);

        $result = '';
        if ($evaluateCondition($condition, $variables)) {
            $result = $render($trueContent, $variables, $depth + 1);
        } else {
            $result = $render($falseContent, $variables, $depth + 1);
        }

        return substr($content, 0, $startPos) . $result . substr($content, $pos);
    }
}
