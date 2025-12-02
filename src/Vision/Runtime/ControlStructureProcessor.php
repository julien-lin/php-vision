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
        if (!preg_match('/{%\s*for\s+(\w+)\s+in\s+(\w+(?:\.\w+)*)\s*%}/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $startPos = $matches[0][1];
        $startLen = strlen($matches[0][0]);
        $itemVar = $matches[1][0];
        $arrayVar = $matches[2][0];

        $pos = $startPos + $startLen;
        $level = 1;
        $loopContentParts = [];

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
                $loopContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                $pos = $endTag + 2;
            } elseif (preg_match(self::REGEX_ENDFOR_TAG, $tag)) {
                $level--;
                if ($level === 0) {
                    $loopContentParts[] = substr($content, $pos, $nextFor - $pos);
                    $pos = $endTag + 2;
                    break;
                } else {
                    $loopContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                    $pos = $endTag + 2;
                }
            } else {
                $loopContentParts[] = substr($content, $pos, $nextFor - $pos + strlen($tag));
                $pos = $endTag + 2;
            }
        }

        if ($level !== 0) {
            return $content;
        }

        $loopContent = implode('', $loopContentParts);

        $arrayValue = $resolve($variables, $arrayVar);
        $result = '';

        if (is_array($arrayValue) || $arrayValue instanceof \Traversable) {
            if ($arrayValue instanceof \Traversable && !is_array($arrayValue)) {
                $arrayValue = iterator_to_array($arrayValue, true);
            }

            $arrayCount = count($arrayValue);
            $index = 0;

            foreach ($arrayValue as $item) {
                $loopVariables = array_merge($variables, [
                    $itemVar => $item,
                    'loop' => [
                        'index' => $index + 1,
                        'index0' => $index,
                        'first' => $index === 0,
                        'last' => $index === $arrayCount - 1,
                        'length' => $arrayCount,
                    ],
                ]);
                $result .= $render($loopContent, $loopVariables, $depth + 1);
                $index++;
            }
        }

        return substr($content, 0, $startPos) . $result . substr($content, $pos);
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
