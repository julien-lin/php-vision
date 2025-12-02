# PHP Vision

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-204%20passed-success.svg)](tests/)

[🇫🇷 Read in French](README.fr.md) | [🇬🇧 Read in English](README.md)

---

**Modern, secure, and blazing-fast PHP template engine** with advanced architecture for professional applications.

Vision combines simplicity with enterprise-grade performance through its **optional compilation pipeline** that can achieve **95%+ performance improvement** over traditional template rendering.

## ✨ Key Features

- 🚀 **Blazing Fast** - Optional compilation pipeline (0.5ms vs 17ms average)
- ⚡ **Fragment Caching** - Cache components individually for 50-80% performance boost
- 🔒 **Secure by Default** - Auto-escaping, path traversal protection, XSS prevention
- 🎯 **Simple Syntax** - Variables `{{ var }}`, filters `|upper`, structures `{% if %}`
- 🏗️ **Modular Architecture** - 7 independent modules (Parser, Compiler, Cache, Filters, Runtime)
- 🧪 **Fully Tested** - 204 tests, 419 assertions, 100% functional coverage
- 🎨 **Extensible** - Custom filters, functions, and processors
- 📦 **Zero Dependencies** - Standalone, no external packages required
- 💪 **PHP 8.0+** - Modern PHP with strict typing

## 📊 Performance Comparison

| Scenario | Traditional | Compiled (cached) | Improvement |
|----------|-------------|-------------------|-------------|
| Simple template | 1.4ms | 0.1ms | **93%** |
| Complex template | 17ms | 0.5ms | **97%** |
| 1000 iterations | 120ms | 2ms | **98%** |

## 🚀 Installation

```bash
composer require julienlinard/php-vision
```

**Requirements**: PHP 8.0 or higher (tested up to PHP 8.5)

## ⚡ Quick Start

### Basic Usage (Legacy Pipeline)

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Vision\Vision;

// Initialize with template directory
$vision = new Vision('/path/to/templates');

// Render a template file
$html = $vision->render('welcome', [
    'name' => 'Julien',
    'title' => 'Welcome'
]);

echo $html;
```

### High-Performance Setup (Compiled Pipeline) 🚀

For **maximum performance** in production, use the optional compilation pipeline:

```php
<?php
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;

$vision = new Vision('/path/to/templates');

// Enable rendering cache
$vision->setCache(true, '/path/to/cache', 3600);

// Enable compilation pipeline (recommended for production)
$vision->setParser(new TemplateParser());
$vision->setCompiler(new TemplateCompiler());
$vision->setCacheManager(new CacheManager('/path/to/compiled-cache', 86400));

// First render: parses + compiles + caches (~17ms)
// Subsequent renders: executes compiled PHP (~0.5ms) - 97% faster!
$html = $vision->render('welcome', ['name' => 'Julien']);
```

### Fragment Caching for Components ⚡

Cache individual components to avoid re-rendering with identical props:

```php
<?php
use JulienLinard\Vision\Vision;

$vision = new Vision('/path/to/templates');

// Enable fragment caching for components (50-80% faster)
$vision->setFragmentCacheConfig(
    enabled: true,
    cacheDir: '/path/to/cache/fragments',
    ttl: 3600  // 1 hour
);

// Components are automatically cached by name + props hash
// First render: parses + renders + caches
// Subsequent renders with same props: returns cached HTML
echo $vision->renderString('{{ component("Button", buttonProps) }}', [
    'buttonProps' => ['label' => 'Save', 'variant' => 'primary']
]);
```

**CLI Management:**

```bash
# Clear fragment cache
./vendor/bin/vision fragment:clear --cache=/path/to/cache/fragments

# View fragment cache statistics
./vendor/bin/vision fragment:stats --cache=/path/to/cache/fragments
```

### Inline Filters (15-30% Faster) ⚡

Vision automatically compiles common filters to native PHP functions instead of calling the FilterManager, providing **15-30% performance improvement** on filter-heavy templates.

**How it works:**

```php
// Template syntax
{{ name|upper }}        // Compiled to: strtoupper($name)
{{ text|trim }}         // Compiled to: trim($text)
{{ data|json }}         // Compiled to: json_encode($data)
{{ list|length }}       // Compiled to: count($list)
{{ html|escape }}       // Compiled to: htmlspecialchars($html, ENT_QUOTES, 'UTF-8')
{{ text|lower }}        // Compiled to: strtolower($text)
```

**Inlineable filters** (no runtime overhead):
- `upper` → `strtoupper()`
- `lower` → `strtolower()`
- `trim` → `trim()`
- `escape` → `htmlspecialchars()`
- `length` → `count()` or `strlen()`
- `json` → `json_encode()`

**Non-inlineable filters** (still use FilterManager):
- `default` - Requires parameter evaluation
- `date` - Complex formatting with parameters
- `number` - Complex formatting with parameters

**Automatic optimization** - No configuration needed! The compiler automatically detects and inlines supported filters during template compilation.

### Direct String Rendering

```php
$vision = new Vision();

$template = 'Hello {{ name|upper }}!';
$html = $vision->renderString($template, ['name' => 'julien']);
// Output: "Hello JULIEN!"
```

## 🏗️ Architecture

Vision features a modular architecture with clear separation of concerns:

```
Vision (Orchestrator)
├── Parser          - Tokenization & AST building
├── Compiler        - AST to PHP compilation
├── CacheManager    - Multi-level caching (parsed + compiled)
├── FilterManager   - Filter registry & application
├── VariableResolver - Nested variable resolution
└── ControlStructureProcessor - For/If handling
```

**Two Rendering Pipelines:**

1. **Legacy Pipeline**: Template → Parse → Render → Output
2. **Compiled Pipeline**: Template → Parse → Compile → Cache → Execute (95%+ faster on cache hit)

## 📋 Features Reference

- ✅ **Variables** - `{{ variable }}` with auto-escaping
- ✅ **Nested Access** - `{{ user.profile.name }}` for deep properties
- ✅ **Filters** - Pipe syntax `{{ name|upper|trim }}` with 10+ built-in filters
- ✅ **Control Structures** - `{% if %}`, `{% else %}`, `{% for %}` loops
- ✅ **Comparison Operators** - `==`, `!=`, `>`, `<`, `>=`, `<=`
- ✅ **Loop Variables** - `loop.index`, `loop.first`, `loop.last`
- ✅ **Object Support** - Getters, public properties, magic methods
- ✅ **Custom Filters** - Easy filter creation via interface
- ✅ **Custom Functions** - Register callable functions
- ✅ **XSS Protection** - Auto-escaping enabled by default
- ✅ **Smart Caching** - Multi-level with TTL and automatic invalidation
- ✅ **Fragment Caching** - Cache components by props for massive performance gains
- ✅ **Constant Folding** - Pre-calculate constant expressions at compile time (10-20% faster)
- ✅ **Inline Filters** - Compile common filters to native PHP (15-30% faster)
- ✅ **Compilation** - Optional PHP compilation for extreme performance
- ✅ **CLI Tools** - Cache management, compilation, and statistics commands

## 📖 Documentation

### Variables

Variables are displayed with the syntax `{{ variable }}` :

```php
$template = 'Hello {{ name }} !';
$html = $vision->renderString($template, ['name' => 'Julien']);
// Result: "Hello Julien !"
```

### Supported File Extensions

When calling `render('template')` without an extension, Vision will try the following in order:

1. `.html.vis` (recommended for Vision templates)
2. `.vis`
3. `.php`
4. `.html`

### Simple Includes (Partials)

Vision provides built-in functions to include other templates:

```php
// Render a partial with explicit variables
{{ template("partials/header", headerData) }}

// Alias
{{ include("partials/footer", footerData) }}
```

Note: you must pass the variables explicitly (e.g., `headerData`), as Vision does not implicitly capture the parent scope for includes.

### Supported File Extensions

When calling `render('template')` without an extension, Vision will try the following in order:

1. `.html.vis` (recommended for Vision templates)
2. `.vis`
3. `.php`
4. `.html`

Examples:

```php
// Automatically loads templates/welcome.html.vis if present
$vision->render('welcome');

// Explicitly load a .vis file
$vision->render('email/welcome.vis');
```

### Nested variables

You can access nested properties with dot notation :

```php
$template = '{{ user.firstname }} {{ user.lastname }}';
$html = $vision->renderString($template, [
    'user' => [
        'firstname' => 'Julien',
        'lastname' => 'Linard',
    ],
]);
// Result: "Julien Linard"
```

### Filters

Filters allow you to transform variables. Use the pipe `|` to chain multiple filters :

```php
$template = '{{ name|upper|trim }}';
$html = $vision->renderString($template, ['name' => '  julien  ']);
// Result: "JULIEN"
```

#### Available filters

##### upper
Converts to uppercase.

```php
{{ name|upper }}
```

##### lower
Converts to lowercase.

```php
{{ name|lower }}
```

##### trim
Removes spaces at the beginning and end.

```php
{{ name|trim }}
```

##### escape
Escapes HTML characters (XSS protection).

```php
{{ content|escape }}
```

##### default
Provides a default value if the variable is empty.

```php
{{ name|default:"Anonymous" }}
```

##### date
Formats a date.

```php
{{ date|date:"Y-m-d" }}
{{ date|date:"d/m/Y H:i" }}
```

##### number
Formats a number.

```php
{{ price|number:2 }}           // 2 decimals
{{ price|number:2:".":"," }}    // Custom format
```

##### length
Returns the length of a string or array.

```php
{{ name|length }}
{{ items|length }}
```

##### json
Encodes a value to JSON.

```php
{{ data|json }}
```

### Control structures

#### Conditions {% if %}

```php
$template = <<<'TEMPLATE'
{% if isActive %}
    <p>Account active</p>
{% else %}
    <p>Account inactive</p>
{% endif %}
TEMPLATE;

$html = $vision->renderString($template, ['isActive' => true]);
```

#### Comparison operators

```php
{% if age >= 18 %}
    <p>Adult</p>
{% endif %}

{% if status == "active" %}
    <p>Active</p>
{% endif %}
```

#### Loops {% for %}

```php
$template = <<<'TEMPLATE'
<ul>
{% for user in users %}
    <li>{{ user.name }} - {{ user.email }}</li>
{% endfor %}
</ul>
TEMPLATE;

$html = $vision->renderString($template, [
    'users' => [
        ['name' => 'Julien', 'email' => 'julien@example.com'],
        ['name' => 'Marie', 'email' => 'marie@example.com'],
    ],
]);
```

#### Loop variables

In a loop, you have access to the `loop` variable :

- `loop.index` : Current index (starts at 1)
- `loop.index0` : Current index (starts at 0)
- `loop.first` : `true` if it's the first iteration
- `loop.last` : `true` if it's the last iteration
- `loop.length` : Total number of items

```php
{% for item in items %}
    {{ loop.index }}: {{ item }}
    {% if loop.first %}First item{% endif %}
    {% if loop.last %}Last item{% endif %}
{% endfor %}
```

### Auto escaping

By default, Vision automatically escapes all variables to protect against XSS attacks :

```php
$vision = new Vision('', true); // Escaping enabled (default)

$template = '{{ content }}';
$html = $vision->renderString($template, [
    'content' => '<script>alert("xss")</script>'
]);
// Result: "&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;"
```

To disable auto escaping :

```php
$vision = new Vision('', false);
// Or
$vision->setAutoEscape(false);
```

### Custom filters

You can create your own filters :

```php
use JulienLinard\Vision\Filters\AbstractFilter;

class ReverseFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'reverse';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        return strrev($value);
    }
}

$vision = new Vision();
$vision->registerFilter(new ReverseFilter());

$template = '{{ name|reverse }}';
$html = $vision->renderString($template, ['name' => 'Julien']);
// Result: "neiluJ"
```

### Custom functions

You can register custom functions :

```php
$vision = new Vision();
$vision->registerFunction('uppercase', function ($text) {
    return strtoupper($text);
});

$vision->registerFunction('concat', function ($a, $b) {
    return $a . $b;
});

$template = '{{ uppercase("hello") }} - {{ concat("a", "b") }}';
$html = $vision->renderString($template, []);
// Result: "HELLO - ab"
```

## 📚 API Reference

### Vision

#### `__construct(string $templateDir = '', bool $autoEscape = true)`

Creates a new Vision instance.

```php
$vision = new Vision('/path/to/templates', true);
```

#### `render(string $template, array $variables = []): string`

Renders a template from a file.

```php
$html = $vision->render('welcome', ['name' => 'Julien']);
```

#### `renderString(string $content, array $variables = []): string`

Renders a template string directly.

```php
$html = $vision->renderString('{{ name }}', ['name' => 'Julien']);
```

#### `registerFilter(FilterInterface $filter): self`

Registers a custom filter.

```php
$vision->registerFilter(new CustomFilter());
```

#### `registerFunction(string $name, callable $callback): self`

Registers a custom function.

```php
$vision->registerFunction('custom', function ($arg) {
    return strtoupper($arg);
});
```

#### `setAutoEscape(bool $enabled): self`

Enables or disables auto escaping.

```php
$vision->setAutoEscape(false);
```

#### `setCache(bool $enabled, ?string $cacheDir = null): self`

Enables or disables caching (coming soon).

```php
$vision->setCache(true, '/cache/path');
```

## 🔒 Security

Vision is built with security as a top priority:

- ✅ **Auto-escaping** - All variables escaped by default (XSS protection)
- ✅ **Path Traversal Protection** - Strict template path validation with `realpath()`
- ✅ **Function Validation** - Only whitelisted function names allowed
- ✅ **Object Injection Prevention** - Safe object serialization in cache
- ✅ **ReDoS Protection** - Regex patterns with limited quantifiers
- ✅ **Race Condition Safe** - File locking with timeout on cache operations

```php
// Auto-escaping is enabled by default
$vision = new Vision('', true);

$html = $vision->renderString('{{ content }}', [
    'content' => '<script>alert("xss")</script>'
]);
// Output: "&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;"
```

### Security Best Practices

1. **Keep auto-escaping enabled** in production
2. **Use the `escape` filter** explicitly for critical output
3. **Validate all user input** before passing to templates
4. **Enable caching** to reduce parsing overhead
5. **Use compiled pipeline** for additional security through code generation

## 🎯 Complete Example

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Vision\Vision;

$vision = new Vision(__DIR__ . '/templates');

$template = <<<'TEMPLATE'
<!DOCTYPE html>
<html>
<head>
    <title>{{ title|upper }}</title>
</head>
<body>
    <h1>Welcome {{ user.name|trim }} !</h1>
    
    {% if user.isActive %}
        <p>Your account is active.</p>
    {% else %}
        <p>Your account is inactive.</p>
    {% endif %}
    
    {% if posts %}
        <h2>Posts ({{ posts|length }})</h2>
        <ul>
        {% for post in posts %}
            <li>
                <strong>{{ post.title }}</strong>
                <small>{{ post.date|date:"d/m/Y" }}</small>
            </li>
        {% endfor %}
        </ul>
    {% else %}
        <p>No posts available.</p>
    {% endif %}
</body>
</html>
TEMPLATE;

$html = $vision->renderString($template, [
    'title' => 'My Site',
    'user' => [
        'name' => '  Julien Linard  ',
        'isActive' => true,
    ],
    'posts' => [
        [
            'title' => 'First post',
            'date' => '2025-01-15',
        ],
        [
            'title' => 'Second post',
            'date' => '2025-01-20',
        ],
    ],
]);

echo $html;
```

## 🚀 Advanced Usage

### Custom Filters

Create powerful custom filters by implementing `FilterInterface`:

```php
use JulienLinard\Vision\Filters\AbstractFilter;

class SlugifyFilter extends AbstractFilter
{
    public function getName(): string
    {
        return 'slugify';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        
        return trim($slug, '-');
    }
}

$vision->registerFilter(new SlugifyFilter());

// Usage: {{ title|slugify }}
// "Hello World!" becomes "hello-world"
```

### Custom Functions

Register custom functions for template logic:

```php
$vision->registerFunction('asset', function ($path) {
    return '/assets/' . ltrim($path, '/');
});

$vision->registerFunction('trans', function ($key, $params = []) {
    // Your translation logic here
    return __($key, $params);
});

// Usage: {{ asset("css/style.css") }}
// Usage: {{ trans("welcome.message") }}
```

### Production Configuration

Recommended configuration for production environments:

```php
<?php
// config/template.php

use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;

$vision = new Vision(
    templateDir: __DIR__ . '/../templates',
    autoEscape: true  // XSS protection
);

// Enable rendering cache (24h TTL)
$vision->setCache(
    enabled: true,
    cacheDir: __DIR__ . '/../var/cache/templates',
    ttl: 86400
);

// Enable compilation pipeline for maximum performance
if (getenv('APP_ENV') === 'production') {
    $vision->setParser(new TemplateParser());
    $vision->setCompiler(new TemplateCompiler());
    $vision->setCacheManager(new CacheManager(
        cacheDir: __DIR__ . '/../var/cache/compiled',
        ttl: 604800  // 7 days for compiled templates
    ));
}

// Register your custom filters and functions
$vision->registerFilter(new SlugifyFilter());
$vision->registerFunction('asset', fn($path) => '/assets/' . $path);

return $vision;
```

### Cache Management

```php
// Clear expired cache entries
$deletedFiles = $vision->clearCache(); // Uses default TTL
$deletedFiles = $vision->clearCache(3600); // Clear entries older than 1 hour
$deletedFiles = $vision->clearCache(0); // Clear all cache

// For compiled cache (CacheManager)
$stats = $cacheManager->getStats();
// Returns: ['total' => 42, 'size' => 1048576, 'oldest' => 1638316800]

$cacheManager->clearAll(); // Clear all compiled templates
$cacheManager->clear(3600); // Clear compiled templates older than 1 hour
```

## 🧪 Testing

Vision comes with comprehensive test coverage:

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suite
./vendor/bin/phpunit tests/SecurityTest.php
```

**Test Statistics:**
- 116 tests across 13 test suites
- 261 assertions
- 100% functional coverage
- Tests for: Security, Performance, Caching, Filters, Loops, Conditions, Objects, Parser, Compiler

## 📚 API Reference

### Vision Class

#### Constructor

```php
public function __construct(
    string $templateDir = '',
    bool $autoEscape = true
)
```

#### Methods

##### `render(string $template, array $variables = []): string`

Renders a template file with the given variables.

```php
$html = $vision->render('page/home', [
    'title' => 'Welcome',
    'user' => $currentUser
]);
```

##### `renderString(string $content, array $variables = [], int $depth = 0): string`

Renders a template string directly.

```php
$html = $vision->renderString('Hello {{ name }}!', ['name' => 'World']);
```

##### `registerFilter(FilterInterface $filter): self`

Registers a custom filter.

```php
$vision->registerFilter(new CustomFilter());
```

##### `registerFunction(string $name, callable $callback): self`

Registers a custom function.

```php
$vision->registerFunction('url', fn($path) => "https://example.com/$path");
```

##### `setAutoEscape(bool $enabled): self`

Controls automatic HTML escaping.

```php
$vision->setAutoEscape(false); // Disable (not recommended)
```

##### `setCache(bool $enabled, ?string $cacheDir = null, int $ttl = 3600): self`

Configures template rendering cache.

```php
$vision->setCache(true, '/tmp/cache', 3600); // 1 hour TTL
```

##### `clearCache(?int $maxAge = null): int`

Clears cached templates and returns number of deleted files.

```php
$count = $vision->clearCache(3600); // Clear cache older than 1 hour
```

##### `setParser(TemplateParser $parser): self`

Enables the parsing subsystem (required for compilation).

```php
$vision->setParser(new TemplateParser());
```

##### `setCompiler(TemplateCompiler $compiler): self`

Enables the compilation subsystem.

```php
$vision->setCompiler(new TemplateCompiler());
```

##### `setCacheManager(CacheManager $cacheManager): self`

Enables the compiled template cache manager.

```php
$vision->setCacheManager(new CacheManager('/tmp/compiled', 86400));
```

### Built-in Filters

| Filter | Description | Example |
|--------|-------------|---------|
| `upper` | Converts to uppercase | `{{ name\|upper }}` |
| `lower` | Converts to lowercase | `{{ name\|lower }}` |
| `trim` | Removes whitespace | `{{ text\|trim }}` |
| `escape` | Escapes HTML entities | `{{ html\|escape }}` |
| `default` | Provides default value | `{{ name\|default:"Guest" }}` |
| `date` | Formats dates | `{{ date\|date:"Y-m-d" }}` |
| `number` | Formats numbers | `{{ price\|number:2 }}` |
| `length` | Returns length | `{{ items\|length }}` |
| `json` | Encodes to JSON | `{{ data\|json }}` |

### Loop Variables

Available inside `{% for %}` loops:

| Variable | Type | Description |
|----------|------|-------------|
| `loop.index` | int | Current iteration (starts at 1) |
| `loop.index0` | int | Current iteration (starts at 0) |
| `loop.first` | bool | True on first iteration |
| `loop.last` | bool | True on last iteration |
| `loop.length` | int | Total number of items |

## 💡 Performance Tips

1. **Enable Compilation Pipeline** - Use Parser + Compiler + CacheManager for 95%+ speed improvement
2. **Use Appropriate TTL** - Longer TTL for stable templates, shorter for frequently changing ones
3. **Minimize Nested Loops** - Keep recursion depth under 20 for optimal performance
4. **Cache Template Objects** - Reuse Vision instance across requests (singleton pattern)
5. **Pre-compile in Production** - Warm up cache after deployment

### Benchmarks

Real-world performance measurements (PHP 8.5.0):

| Operation | Time | Memory |
|-----------|------|--------|
| Simple template (legacy) | 1.4ms | 350KB |
| Complex template (legacy) | 17ms | 1.2MB |
| 1000 loop iterations | 120ms | 2.5MB |
| **Compiled (cache hit)** | **0.5ms** | **200KB** |
| **Compiled (first render)** | **20ms** | **1.5MB** |

## 🔧 Configuration Options

### Environment Variables

```bash
# .env example
VISION_CACHE_ENABLED=true
VISION_CACHE_DIR=/var/cache/vision
VISION_CACHE_TTL=3600
VISION_AUTO_ESCAPE=true
VISION_COMPILED_ENABLED=true
```

### Framework Integration

#### Laravel

```php
// config/view.php
'engines' => [
    'vision' => JulienLinard\Vision\Vision::class,
],
```

#### Symfony

```yaml
# config/services.yaml
services:
    JulienLinard\Vision\Vision:
        arguments:
            $templateDir: '%kernel.project_dir%/templates'
            $autoEscape: true
```

## 🐛 Troubleshooting

### Common Issues

**Cache not working**
```php
// Ensure cache directory is writable
chmod 775 var/cache/templates
```

**Templates not found**
```php
// Check template directory path
$vision = new Vision(realpath(__DIR__ . '/templates'));
```

**Compilation errors**
```php
// Clear compiled cache
$cacheManager->clearAll();
```

**Performance issues**
```php
// Enable compilation pipeline
$vision->setParser(new TemplateParser());
$vision->setCompiler(new TemplateCompiler());
$vision->setCacheManager(new CacheManager('/tmp/compiled'));
```

## 📖 Migration Guide

### From Twig

Vision uses similar syntax to Twig for easy migration:

| Twig | Vision | Notes |
|------|--------|-------|
| `{{ var }}` | `{{ var }}` | ✅ Identical |
| `{{ var\|upper }}` | `{{ var\|upper }}` | ✅ Identical |
| `{% if var %}` | `{% if var %}` | ✅ Identical |
| `{% for item in items %}` | `{% for item in items %}` | ✅ Identical |
| `{% extends 'base' %}` | ❌ Not supported | Use includes instead |
| `{% block name %}` | ❌ Not supported | Use partials |
| `{% include 'partial' %}` | ⚠️ Coming soon | Roadmap feature |

## 🗺️ Roadmap

- [ ] Template inheritance (`{% extends %}`, `{% block %}`)
- [ ] Include/import system (`{% include %}`)
- [ ] Macro support (`{% macro %}`)
- [ ] Whitespace control (`{%-` and `-%}`)
- [ ] Ternary operator (`{{ var ? 'yes' : 'no' }}`)
- [ ] Array/object filters (`{{ items|first }}`, `{{ items|last }}`)
- [ ] Math operations in templates (`{{ price * 1.2 }}`)
- [ ] String interpolation (`{{ "Hello #{name}" }}`)

## 📝 License

MIT License - see [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please read our [contributing guidelines](CONTRIBUTING.md) before submitting PRs.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/julienlinard/php-vision.git
cd php-vision

# Install dependencies
composer install

# Run tests
composer test

# Run code style checks
composer cs

# Run static analysis
composer analyze
```

## 💝 Support This Project

If you find PHP Vision useful, consider supporting its development:

- ⭐ Star the repository
- 🐛 Report bugs and suggest features
- 💻 Contribute code improvements
- 📖 Improve documentation
- 💰 [Become a sponsor](https://github.com/sponsors/julien-lin)

## 🙏 Acknowledgments

- Inspired by Twig, Blade, and Smarty
- Built with modern PHP 8.0+ features
- Community tested and production proven

---

**Developed with ❤️ by [Julien Linard](https://github.com/julien-lin)**

For questions, issues, or feature requests, please [open an issue](https://github.com/julienlinard/php-vision/issues) on GitHub.
