<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use JulienLinard\Vision\Compiler\FilterInliner;
use PHPUnit\Framework\TestCase;

class FilterInlinerTest extends TestCase
{
    private FilterInliner $inliner;

    protected function setUp(): void
    {
        $this->inliner = new FilterInliner();
    }

    // ===== Tests canInline =====

    public function testCanInlineUpper(): void
    {
        $this->assertTrue($this->inliner->canInline('upper'));
    }

    public function testCanInlineLower(): void
    {
        $this->assertTrue($this->inliner->canInline('lower'));
    }

    public function testCanInlineTrim(): void
    {
        $this->assertTrue($this->inliner->canInline('trim'));
    }

    public function testCanInlineEscape(): void
    {
        $this->assertTrue($this->inliner->canInline('escape'));
    }

    public function testCanInlineLength(): void
    {
        $this->assertTrue($this->inliner->canInline('length'));
    }

    public function testCanInlineJson(): void
    {
        $this->assertTrue($this->inliner->canInline('json'));
    }

    public function testCannotInlineDefault(): void
    {
        $this->assertFalse($this->inliner->canInline('default'));
    }

    public function testCannotInlineDate(): void
    {
        $this->assertFalse($this->inliner->canInline('date'));
    }

    public function testCannotInlineNumber(): void
    {
        $this->assertFalse($this->inliner->canInline('number'));
    }

    public function testCannotInlineUnknownFilter(): void
    {
        $this->assertFalse($this->inliner->canInline('unknownfilter'));
    }

    // ===== Tests inline =====

    public function testInlineUpper(): void
    {
        $result = $this->inliner->inline('upper', '$value');
        $this->assertEquals('strtoupper($value)', $result);
    }

    public function testInlineLower(): void
    {
        $result = $this->inliner->inline('lower', '$value');
        $this->assertEquals('strtolower($value)', $result);
    }

    public function testInlineTrim(): void
    {
        $result = $this->inliner->inline('trim', '$value');
        $this->assertEquals('trim($value)', $result);
    }

    public function testInlineEscape(): void
    {
        $result = $this->inliner->inline('escape', '$value');
        $this->assertStringContainsString('htmlspecialchars', $result);
        $this->assertStringContainsString('$value', $result);
    }

    public function testInlineLength(): void
    {
        $result = $this->inliner->inline('length', '$value');
        $this->assertStringContainsString('count', $result);
        $this->assertStringContainsString('strlen', $result);
    }

    public function testInlineJson(): void
    {
        $result = $this->inliner->inline('json', '$value');
        $this->assertStringContainsString('json_encode', $result);
    }

    public function testInlineWithComplexExpression(): void
    {
        $result = $this->inliner->inline('upper', '$user->getName()');
        $this->assertEquals('strtoupper($user->getName())', $result);
    }

    public function testInlineThrowsExceptionForNonInlineable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->inliner->inline('default', '$value');
    }

    // ===== Tests compileFilterChain =====

    public function testCompileFilterChainSingleInlineable(): void
    {
        $code = $this->inliner->compileFilterChain('$__value', ['upper'], '    ');

        $this->assertStringContainsString('strtoupper', $code);
        $this->assertStringContainsString('// Inlined: upper', $code);
    }

    public function testCompileFilterChainMultipleInlineable(): void
    {
        $code = $this->inliner->compileFilterChain('$__value', ['trim', 'upper'], '    ');

        $this->assertStringContainsString('trim', $code);
        $this->assertStringContainsString('strtoupper', $code);
        $this->assertStringContainsString('// Inlined: trim', $code);
        $this->assertStringContainsString('// Inlined: upper', $code);
    }

    public function testCompileFilterChainMixed(): void
    {
        $code = $this->inliner->compileFilterChain('$__value', ['upper', 'default:Anonymous'], '    ');

        // upper should be inlined
        $this->assertStringContainsString('strtoupper', $code);
        $this->assertStringContainsString('// Inlined: upper', $code);

        // default should use FilterManager
        $this->assertStringContainsString('applyFilter', $code);
        $this->assertStringContainsString('default:Anonymous', $code);
    }

    public function testCompileFilterChainEmpty(): void
    {
        $code = $this->inliner->compileFilterChain('$__value', [], '    ');
        $this->assertEquals('', $code);
    }

    public function testCompileFilterChainWithEmptyStrings(): void
    {
        $code = $this->inliner->compileFilterChain('$__value', ['upper', '', 'lower'], '    ');

        // Should skip empty filter
        $this->assertStringContainsString('strtoupper', $code);
        $this->assertStringContainsString('strtolower', $code);
    }

    public function testCompileFilterChainIndentation(): void
    {
        $code = $this->inliner->compileFilterChain('$__value', ['upper'], '        ');

        // Check indentation (without trimming)
        $lines = explode("\n", $code);
        $this->assertStringStartsWith('        ', $lines[0]);
    }

    // ===== Tests registerInlineFilter =====

    public function testRegisterInlineFilter(): void
    {
        $this->inliner->registerInlineFilter('reverse', 'strrev(%s)');

        $this->assertTrue($this->inliner->canInline('reverse'));
        $result = $this->inliner->inline('reverse', '$value');
        $this->assertEquals('strrev($value)', $result);
    }

    // ===== Tests getInlineableFilters =====

    public function testGetInlineableFilters(): void
    {
        $filters = $this->inliner->getInlineableFilters();

        $this->assertIsArray($filters);
        $this->assertContains('upper', $filters);
        $this->assertContains('lower', $filters);
        $this->assertContains('trim', $filters);
        $this->assertContains('escape', $filters);
        $this->assertContains('length', $filters);
        $this->assertContains('json', $filters);
    }

    // ===== Tests analyzeInlineablility =====

    public function testAnalyzeInlineablility(): void
    {
        $filters = ['upper', 'lower', 'default', 'trim', 'date'];

        $stats = $this->inliner->analyzeInlineablility($filters);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['inlined']); // upper, lower, trim
        $this->assertEquals(60.0, $stats['percentage']);
    }

    public function testAnalyzeInlineablilityEmpty(): void
    {
        $stats = $this->inliner->analyzeInlineablility([]);

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['inlined']);
        $this->assertEquals(0.0, $stats['percentage']);
    }

    public function testAnalyzeInlineablilityAllInlineable(): void
    {
        $filters = ['upper', 'lower', 'trim'];

        $stats = $this->inliner->analyzeInlineablility($filters);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(3, $stats['inlined']);
        $this->assertEquals(100.0, $stats['percentage']);
    }

    public function testAnalyzeInlineablilityNoneInlineable(): void
    {
        $filters = ['default', 'date', 'number'];

        $stats = $this->inliner->analyzeInlineablility($filters);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(0, $stats['inlined']);
        $this->assertEquals(0.0, $stats['percentage']);
    }

    // ===== Integration Tests =====

    public function testRealWorldFilterChain(): void
    {
        // Simulate: {{ user.name|trim|upper|escape }}
        $code = $this->inliner->compileFilterChain('$__value', ['trim', 'upper', 'escape'], '    ');

        // All three should be inlined
        $this->assertStringContainsString('trim($__value)', $code);
        $this->assertStringContainsString('strtoupper($__value)', $code);
        $this->assertStringContainsString('htmlspecialchars', $code);

        // Should NOT use applyFilter
        $this->assertStringNotContainsString('applyFilter', $code);
    }

    public function testComplexMixedFilterChain(): void
    {
        // Simulate: {{ count|number:2|upper }}
        $code = $this->inliner->compileFilterChain('$__value', ['number:2', 'upper'], '    ');

        // number should use FilterManager
        $this->assertStringContainsString('applyFilter', $code);
        $this->assertStringContainsString('number:2', $code);

        // upper should be inlined
        $this->assertStringContainsString('strtoupper', $code);
        $this->assertStringContainsString('// Inlined: upper', $code);
    }
}
