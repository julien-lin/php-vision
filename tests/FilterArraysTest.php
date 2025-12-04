<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;

class FilterArraysTest extends TestCase
{
    private Vision $vision;
    private string $templateDir;

    protected function setUp(): void
    {
        $this->templateDir = sys_get_temp_dir() . '/vision_' . uniqid();
        mkdir($this->templateDir, 0777, true);
        $this->vision = new Vision($this->templateDir);
    }

    protected function tearDown(): void
    {
        // Cleanup
        $files = glob($this->templateDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->templateDir);
    }

    private function createTemplate(string $name, string $content): void
    {
        file_put_contents($this->templateDir . '/' . $name, $content);
    }

    // Batch filter: group items into chunks
    public function testBatchFilter(): void
    {
        $this->createTemplate('test.html', "{% for batch in items|batch(2) %}[{% for item in batch %}{{ item }}{% endfor %}]{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => ['a', 'b', 'c', 'd', 'e']]);
        // Batches of 2: [ab][cd][e]
        $this->assertStringContainsString('[ab]', $result);
        $this->assertStringContainsString('[cd]', $result);
        $this->assertStringContainsString('[e]', $result);
    }

    // Filter filter: keep only items matching condition
    public function testFilterFilter(): void
    {
        $this->createTemplate('test.html', "{% for item in items|filter %}{{ item }}{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 0, 2, null, 3, false]]);
        // Filter removes falsy values: 1, 2, 3
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('3', $result);
        // False and null should not appear
        $this->assertStringNotContainsString('false', $result);
        $this->assertStringNotContainsString('null', $result);
    }

    // Filter with custom callback
    public function testFilterWithCallback(): void
    {
        // Filter numbers > 2
        $this->createTemplate('test.html', "{% for item in items|filter('greaterThan', 2) %}{{ item }}{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4, 5]]);
        $this->assertStringContainsString('3', $result);
        $this->assertStringContainsString('4', $result);
        $this->assertStringContainsString('5', $result);
        $this->assertStringNotContainsString('1', $result);
        $this->assertStringNotContainsString('2', $result);
    }

    // Map filter: transform items
    public function testMapFilter(): void
    {
        $this->createTemplate('test.html', "{% for item in items|map('double') %}{{ item }}{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4]]);
        // Items doubled: 2, 4, 6, 8
        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('4', $result);
        $this->assertStringContainsString('6', $result);
        $this->assertStringContainsString('8', $result);
    }

    // Map with attribute extraction
    public function testMapAttribute(): void
    {
        $this->createTemplate('test.html', "{% for name in users|map('name') %}{{ name }}{% endfor %}");
        $result = $this->vision->render('test.html', [
            'users' => [
                ['name' => 'Alice', 'age' => 30],
                ['name' => 'Bob', 'age' => 25],
                ['name' => 'Charlie', 'age' => 35],
            ]
        ]);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('Bob', $result);
        $this->assertStringContainsString('Charlie', $result);
    }

    // Batch with custom size
    public function testBatchFilterCustomSize(): void
    {
        $this->createTemplate('test.html', "{% for batch in items|batch(3) %}[{{ batch|length }}]{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4, 5]]);
        // Batches of 3: [3][2]
        $this->assertStringContainsString('[3]', $result);
        $this->assertStringContainsString('[2]', $result);
    }

    // Batch with fill character
    public function testBatchFilterWithFill(): void
    {
        $this->createTemplate('test.html', "{% for batch in items|batch(3, 'x') %}[{% for item in batch %}{{ item }}{% endfor %}]{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4, 5]]);
        // Batches of 3, padded with 'x': [123][45x]
        $this->assertStringContainsString('[123]', $result);
        $this->assertStringContainsString('[45x]', $result);
    }

    // Chaining filters: filter then batch
    public function testChainFilterBatch(): void
    {
        $this->createTemplate('test.html', "{% for batch in items|filter|batch(2) %}[{% for item in batch %}{{ item }}{% endfor %}]{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 0, 2, 3, false, 4]]);
        // Filter removes 0 and false: [1, 2, 3, 4]
        // Batch into pairs: [12][34]
        $this->assertStringContainsString('[12]', $result);
        $this->assertStringContainsString('[34]', $result);
    }

    // Chaining filters: map then batch
    public function testChainMapBatch(): void
    {
        $this->createTemplate('test.html', "{% for batch in items|map('double')|batch(2) %}[{% for item in batch %}{{ item }}{% endfor %}]{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => [1, 2, 3, 4]]);
        // Map double: [2, 4, 6, 8]
        // Batch into pairs: [24][68]
        $this->assertStringContainsString('[24]', $result);
        $this->assertStringContainsString('[68]', $result);
    }

    // Empty array handling
    public function testBatchFilterEmpty(): void
    {
        $this->createTemplate('test.html', "Count: {% for batch in items|batch(2) %}1{% endfor %}");
        $result = $this->vision->render('test.html', ['items' => []]);
        $this->assertStringContainsString('Count: ', $result);
        // No batches for empty array
        $this->assertStringNotContainsString('Count: 1', $result);
    }
}
