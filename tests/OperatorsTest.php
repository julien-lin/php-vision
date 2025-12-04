<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class OperatorsTest extends TestCase
{
    private Vision $vision;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/vision_ops_' . uniqid();
        @mkdir($this->tempDir, 0777, true);
        $this->vision = new Vision($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tempDir);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->deleteDir($path) : @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function createTemplate(string $name, string $content): void
    {
        $path = $this->tempDir . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
    }

    // Opérateurs arithmétiques
    public function testAddition(): void
    {
        $this->createTemplate('test.html', "{{ 5 + 3 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('8', $result);
    }

    public function testSubtraction(): void
    {
        $this->createTemplate('test.html', "{{ 10 - 2 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('8', $result);
    }

    public function testMultiplication(): void
    {
        $this->createTemplate('test.html', "{{ 4 * 5 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('20', $result);
    }

    public function testDivision(): void
    {
        $this->createTemplate('test.html', "{{ 20 / 4 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('5', $result);
    }

    public function testModulo(): void
    {
        $this->createTemplate('test.html', "{{ 17 % 5 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('2', $result);
    }

    public function testPower(): void
    {
        $this->createTemplate('test.html', "{{ 2 ** 3 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('8', $result);
    }

    // Opérateurs avec variables
    public function testAdditionWithVariables(): void
    {
        $this->createTemplate('test.html', "{{ a + b }}");
        $result = $this->vision->render('test.html', ['a' => 10, 'b' => 5]);
        $this->assertStringContainsString('15', $result);
    }

    public function testMultiplicationWithVariables(): void
    {
        $this->createTemplate('test.html', "{{ price * quantity }}");
        $result = $this->vision->render('test.html', ['price' => 25, 'quantity' => 4]);
        $this->assertStringContainsString('100', $result);
    }

    // Priorité des opérateurs
    public function testOperatorPriority(): void
    {
        $this->createTemplate('test.html', "{{ 2 + 3 * 4 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('14', $result); // 2 + 12
    }

    public function testOperatorPriorityWithPower(): void
    {
        $this->createTemplate('test.html', "{{ 2 * 3 ** 2 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('18', $result); // 2 * 9
    }

    // Ternaire
    public function testTernaryTrue(): void
    {
        $this->createTemplate('test.html', "{{ 1 ? 'yes' : 'no' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
    }

    public function testTernaryFalse(): void
    {
        $this->createTemplate('test.html', "{{ 0 ? 'yes' : 'no' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('no', $result);
    }

    public function testTernaryWithComparison(): void
    {
        $this->createTemplate('test.html', "{{ age > 18 ? 'adult' : 'minor' }}");
        $result = $this->vision->render('test.html', ['age' => 25]);
        $this->assertStringContainsString('adult', $result);
    }

    public function testTernaryWithComparisonFalse(): void
    {
        $this->createTemplate('test.html', "{{ age > 18 ? 'adult' : 'minor' }}");
        $result = $this->vision->render('test.html', ['age' => 15]);
        $this->assertStringContainsString('minor', $result);
    }

    // Comparaisons
    public function testGreaterThan(): void
    {
        $this->createTemplate('test.html', "{{ 10 > 5 ? 'yes' : 'no' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
    }

    public function testLessThan(): void
    {
        $this->createTemplate('test.html', "{{ 3 < 8 ? 'yes' : 'no' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
    }

    public function testEquality(): void
    {
        $this->createTemplate('test.html', "{{ 5 == 5 ? 'equal' : 'not' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('equal', $result);
    }

    public function testInequality(): void
    {
        $this->createTemplate('test.html', "{{ 5 != 3 ? 'different' : 'same' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('different', $result);
    }

    // Booleens avec opérateurs
    public function testLogicalAnd(): void
    {
        $this->createTemplate('test.html', "{{ 1 && 1 ? 'yes' : 'no' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
    }

    public function testLogicalOr(): void
    {
        $this->createTemplate('test.html', "{{ 0 || 1 ? 'yes' : 'no' }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('yes', $result);
    }

    // Combinaisons
    public function testComplexExpression(): void
    {
        $this->createTemplate('test.html', "{{ (a + b) * c > 50 ? 'big' : 'small' }}");
        $result = $this->vision->render('test.html', ['a' => 10, 'b' => 5, 'c' => 4]);
        $this->assertStringContainsString('big', $result); // (10+5)*4 = 60 > 50
    }

    public function testTernaryWithArithmetic(): void
    {
        $this->createTemplate('test.html', "{{ score >= 50 ? score * 2 : score }}");
        $result = $this->vision->render('test.html', ['score' => 60]);
        $this->assertStringContainsString('120', $result);
    }

    public function testNegation(): void
    {
        $this->createTemplate('test.html', "{{ -5 + 10 }}");
        $result = $this->vision->render('test.html', []);
        $this->assertStringContainsString('5', $result);
    }
}
