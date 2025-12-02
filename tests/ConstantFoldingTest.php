<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use JulienLinard\Vision\Compiler\ConstantFolder;
use PHPUnit\Framework\TestCase;

class ConstantFoldingTest extends TestCase
{
    private ConstantFolder $folder;

    protected function setUp(): void
    {
        $this->folder = new ConstantFolder();
    }

    // ===== Tests Mathématiques =====

    public function testFoldSimpleAddition(): void
    {
        $result = $this->folder->fold('2 + 3');
        $this->assertEquals('5', $result);
    }

    public function testFoldSimpleMultiplication(): void
    {
        $result = $this->folder->fold('24 * 60 * 60');
        $this->assertEquals('86400', $result);
    }

    public function testFoldComplexMathExpression(): void
    {
        $result = $this->folder->fold('(10 + 5) * 2 - 3');
        $this->assertEquals('27', $result);
    }

    public function testFoldDivision(): void
    {
        $result = $this->folder->fold('100 / 4');
        $this->assertEquals('25', $result);
    }

    public function testFoldModulo(): void
    {
        $result = $this->folder->fold('17 % 5');
        $this->assertEquals('2', $result);
    }

    public function testFoldFloatExpression(): void
    {
        $result = $this->folder->fold('3.14 * 2');
        $this->assertEquals('6.28', $result);
    }

    public function testFoldNegativeNumbers(): void
    {
        $result = $this->folder->fold('-5 + 10');
        $this->assertEquals('5', $result);
    }

    // ===== Tests Concaténation de Chaînes =====
    // Note: String concatenation optimization désactivée pour l'instant
    // Focus sur optimisations mathématiques qui apportent le plus de valeur

    public function testDoesNotOptimizeStringConcatYet(): void
    {
        // String concat n'est pas encore optimisé - feature future
        $expr = '"Hello " ~ "World"';
        $result = $this->folder->fold($expr);
        $this->assertEquals($expr, $result, 'String concat not optimized yet');
    }

    // ===== Tests Booléens =====

    public function testFoldBooleanTrue(): void
    {
        $result = $this->folder->fold('true');
        $this->assertEquals('true', $result);
    }

    public function testFoldBooleanFalse(): void
    {
        $result = $this->folder->fold('false');
        $this->assertEquals('false', $result);
    }

    public function testFoldBooleanAnd(): void
    {
        $result = $this->folder->fold('true && false');
        $this->assertEquals('false', $result);
    }

    public function testFoldBooleanOr(): void
    {
        $result = $this->folder->fold('false || true');
        $this->assertEquals('true', $result);
    }

    public function testFoldBooleanNegation(): void
    {
        $result = $this->folder->fold('!false');
        $this->assertEquals('true', $result);
    }

    public function testFoldComplexBoolean(): void
    {
        $result = $this->folder->fold('true && true || false');
        $this->assertEquals('true', $result);
    }

    // ===== Tests Non-Optimisables (avec variables) =====

    public function testDoesNotFoldExpressionWithVariable(): void
    {
        $expr = 'count + 1';
        $result = $this->folder->fold($expr);
        $this->assertEquals($expr, $result, 'Should not fold expressions with variables');
    }

    public function testDoesNotFoldDottedVariable(): void
    {
        $expr = 'user.age + 10';
        $result = $this->folder->fold($expr);
        $this->assertEquals($expr, $result, 'Should not fold expressions with dotted variables');
    }

    public function testDoesNotFoldVariableInString(): void
    {
        $expr = '"Hello " ~ name';
        $result = $this->folder->fold($expr);
        $this->assertEquals($expr, $result, 'Should not fold string concat with variables');
    }

    // ===== Tests isOptimizable =====

    public function testIsOptimizableMathExpression(): void
    {
        $this->assertTrue($this->folder->isOptimizable('24 * 60 * 60'));
    }

    public function testIsOptimizableBoolean(): void
    {
        $this->assertTrue($this->folder->isOptimizable('true && false'));
    }

    public function testIsNotOptimizableWithVariable(): void
    {
        $this->assertFalse($this->folder->isOptimizable('count + 1'));
    }

    public function testIsNotOptimizableDottedVariable(): void
    {
        $this->assertFalse($this->folder->isOptimizable('user.name'));
    }

    public function testIsNotOptimizablePlainVariable(): void
    {
        $this->assertFalse($this->folder->isOptimizable('name'));
    }

    // ===== Tests analyzeOptimizationPotential =====

    public function testAnalyzeOptimizationPotential(): void
    {
        $expressions = [
            '24 * 60 * 60',      // optimizable
            'count + 1',         // not optimizable
            '100 / 4',           // optimizable
            'user.name',         // not optimizable
            'true && false',     // optimizable
        ];

        $stats = $this->folder->analyzeOptimizationPotential($expressions);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['optimized']);
        $this->assertEquals(60.0, $stats['percentage']);
    }

    public function testAnalyzeOptimizationPotentialEmpty(): void
    {
        $stats = $this->folder->analyzeOptimizationPotential([]);

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['optimized']);
        $this->assertEquals(0.0, $stats['percentage']);
    }

    // ===== Tests Edge Cases =====

    public function testFoldWithWhitespace(): void
    {
        $result = $this->folder->fold('  5   +   3  ');
        $this->assertEquals('8', $result);
    }

    public function testFoldEmptyString(): void
    {
        $result = $this->folder->fold('');
        $this->assertEquals('', $result);
    }

    public function testFoldInvalidExpression(): void
    {
        $expr = '5 + + 3'; // invalid syntax
        $result = $this->folder->fold($expr);
        // Should return original or handle gracefully
        $this->assertIsString($result);
    }

    public function testFoldDivisionByZero(): void
    {
        // Division by zero should not crash
        $result = $this->folder->fold('10 / 0');
        $this->assertIsString($result);
    }

    public function testDoesNotFoldFunctionCalls(): void
    {
        $expr = 'strlen("hello")';
        $result = $this->folder->fold($expr);
        // Should not optimize function calls (contain letters)
        $this->assertEquals($expr, $result);
    }

    // ===== Tests Format =====

    public function testFormatInteger(): void
    {
        $result = $this->folder->fold('5 + 3');
        $this->assertStringNotContainsString('.', $result, 'Integer should not have decimal point');
    }

    public function testFormatFloat(): void
    {
        $result = $this->folder->fold('5.5 + 2.3');
        $this->assertStringContainsString('.', $result, 'Float should have decimal point');
    }

    public function testFormatBoolean(): void
    {
        $result = $this->folder->fold('true');
        $this->assertEquals('true', $result);
        
        $result = $this->folder->fold('false');
        $this->assertEquals('false', $result);
    }

    // ===== Tests Real-World Scenarios =====

    public function testFoldSecondsInDay(): void
    {
        // Common use case: calculate seconds in a day
        $result = $this->folder->fold('24 * 60 * 60');
        $this->assertEquals('86400', $result);
    }

    public function testFoldPercentageCalculation(): void
    {
        $result = $this->folder->fold('100 * 0.25');
        $this->assertEquals('25', $result);
    }

    public function testFoldBytesToMegabytes(): void
    {
        $result = $this->folder->fold('1048576 / 1024 / 1024');
        $this->assertEquals('1', $result);
    }
}
