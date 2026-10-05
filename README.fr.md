# PHP Vision

[![Version PHP](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)](https://php.net)
[![Licence](https://img.shields.io/badge/licence-MIT-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-427%20r%C3%A9ussis-success.svg)](tests/)

[🇫🇷 Lire en français](README.fr.md) | [🇬🇧 Read in English](README.md)

---

**Moteur de templates PHP moderne, sécurisé et ultra-rapide** avec architecture avancée pour applications professionnelles.

Vision allie simplicité et performance de niveau entreprise grâce à son **pipeline de compilation optionnel** qui peut atteindre **plus de 95% d'amélioration des performances** par rapport au rendu traditionnel.

## ✨ Fonctionnalités Clés

- 🚀 **Ultra Rapide** - Pipeline de compilation optionnel (0,5ms vs 17ms en moyenne)
- ⚡ **Cache de Fragments** - Cache les composants individuellement pour 50-80% de gain
- 🔒 **Sécurisé par Défaut** - Échappement automatique, protection path traversal, prévention XSS
- 🎯 **Syntaxe Simple** - Variables `{{ var }}`, filtres `|upper`, structures `{% if %}`
- 🏗️ **Architecture Modulaire** - 7 modules indépendants (Parser, Compiler, Cache, Filters, Runtime)
- 🧪 **Entièrement Testé** - 427 tests, 937 assertions, couverture fonctionnelle 100%
- 🎨 **Extensible** - Filtres, fonctions et processeurs personnalisés
- 📦 **Zéro Dépendance** - Autonome, aucun package externe requis
- 💪 **PHP 8.0+** - PHP moderne avec typage strict

## 📊 Comparaison de Performance

| Scénario | Traditionnel | Compilé (cache) | Amélioration |
|----------|--------------|-----------------|--------------|
| Template simple | 1,4ms | 0,1ms | **93%** |
| Template complexe | 17ms | 0,5ms | **97%** |
| 1000 itérations | 120ms | 2ms | **98%** |

## 🚀 Installation

```bash
composer require julienlinard/php-vision
```

**Prérequis** : PHP 8.0 ou supérieur (testé jusqu'à PHP 8.5)

## ⚡ Démarrage Rapide

### Utilisation de Base (Pipeline Legacy)

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Vision\Vision;

// Initialiser avec le répertoire des templates
$vision = new Vision('/chemin/vers/templates');

// Rendre un fichier template
$html = $vision->render('welcome', [
    'name' => 'Julien',
    'title' => 'Bienvenue'
]);

echo $html;
```

### Configuration Haute Performance (Pipeline Compilé) 🚀

Pour des **performances maximales** en production, utilisez le pipeline de compilation optionnel :

```php
<?php
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;

$vision = new Vision('/chemin/vers/templates');

// Activer le cache de rendu
$vision->setCache(true, '/chemin/vers/cache', 3600);

// Activer le pipeline de compilation (recommandé en production)
$vision->setParser(new TemplateParser());
$vision->setCompiler(new TemplateCompiler());
$vision->setCacheManager(new CacheManager('/chemin/vers/cache-compile', 86400));

// Premier rendu : parse + compile + cache (~17ms)
// Rendus suivants : exécute le PHP compilé (~0,5ms) - 97% plus rapide !
$html = $vision->render('welcome', ['name' => 'Julien']);
```

### Cache de Fragments pour Composants ⚡

Cachez les composants individuellement pour éviter de re-rendre avec des props identiques :

```php
<?php
use JulienLinard\Vision\Vision;

$vision = new Vision('/chemin/vers/templates');

// Activer le cache de fragments pour composants (50-80% plus rapide)
$vision->setFragmentCacheConfig(
    enabled: true,
    cacheDir: '/chemin/vers/cache/fragments',
    ttl: 3600  // 1 heure
);

// Les composants sont automatiquement cachés par nom + hash des props
// Premier rendu : parse + render + cache
// Rendus suivants avec mêmes props : retourne le HTML caché
echo $vision->renderString('{{ component("Button", buttonProps) }}', [
    'buttonProps' => ['label' => 'Enregistrer', 'variant' => 'primary']
]);
```

**Gestion CLI :**

```bash
# Nettoyer le cache des fragments
./vendor/bin/vision fragment:clear --cache=/chemin/vers/cache/fragments

# Voir les statistiques du cache des fragments
./vendor/bin/vision fragment:stats --cache=/chemin/vers/cache/fragments
```

### Inline Filters (15-30% Plus Rapide) ⚡

Vision compile automatiquement les filtres courants en fonctions PHP natives au lieu d'appeler le FilterManager, offrant une **amélioration de 15-30%** sur les templates avec beaucoup de filtres.

**Comment ça fonctionne :**

```php
// Syntaxe template
{{ name|upper }}        // Compilé en : strtoupper($name)
{{ text|trim }}         // Compilé en : trim($text)
{{ data|json }}         // Compilé en : json_encode($data)
{{ list|length }}       // Compilé en : count($list)
{{ html|escape }}       // Compilé en : htmlspecialchars($html, ENT_QUOTES, 'UTF-8')
{{ text|lower }}        // Compilé en : strtolower($text)
```

**Filtres inlineables** (pas de surcoût runtime) :
- `upper` → `strtoupper()`
- `lower` → `strtolower()`
- `trim` → `trim()`
- `escape` → `htmlspecialchars()`
- `length` → `count()` ou `strlen()`
- `json` → `json_encode()`

**Filtres non-inlineables** (utilisent le FilterManager) :
- `default` - Nécessite évaluation de paramètre
- `date` - Formatage complexe avec paramètres
- `number` - Formatage complexe avec paramètres

**Optimisation automatique** - Aucune configuration nécessaire ! Le compilateur détecte et inline automatiquement les filtres supportés lors de la compilation du template.

### Dead Branch Elimination (5-10% Plus Rapide) ⚡

Vision supprime automatiquement le code inaccessible lors de la compilation, offrant une **amélioration de 5-10%** en éliminant les branches mortes des templates compilés.

**Comment ça fonctionne :**

```php
// Template avec conditions statiques
{% if true %}
    Ceci est toujours affiché
{% endif %}

{% if false %}
    Ceci n'est jamais affiché
{% endif %}

// Sortie compilée (optimisée)
Ceci est toujours affiché

// Le bloc {% if false %} est complètement supprimé !
```

**Optimisations :**

```php
// 1. Élimination {% if true %} → Garde uniquement le contenu
{% if true %}Contenu{% endif %}
// Compilé : Contenu

// 2. Élimination {% if false %} → Supprime le bloc entier
{% if false %}Code mort{% endif %}
// Compilé : (rien)

// 3. Élimination de la branche else quand if est true
{% if true %}Actif{% else %}Mort{% endif %}
// Compilé : Actif

// 4. Garde la branche else quand if est false
{% if false %}Mort{% else %}Actif{% endif %}
// Compilé : Actif

// 5. Gestion des chaînes elseif
{% if false %}A{% elseif true %}B{% else %}C{% endif %}
// Compilé : B

// 6. Préservation des conditions variables (pas d'optimisation)
{% if user.isAdmin %}Panneau admin{% endif %}
// Compilé : (inchangé - évaluation runtime)
```

**Expressions booléennes supportées :**

```php
{% if 1 + 1 == 2 %}Toujours vrai{% endif %}      // Constant folding + élimination
{% if 5 > 3 && 2 < 4 %}Toujours vrai{% endif %}  // Opérateurs booléens
{% if !false %}Toujours vrai{% endif %}           // Négation
```

**Optimisation automatique** - Fonctionne parfaitement avec Constant Folding. Aucune configuration nécessaire !

### Rendu Direct d'une Chaîne

```php
$vision = new Vision();

$template = 'Bonjour {{ name|upper }} !';
$html = $vision->renderString($template, ['name' => 'julien']);
// Résultat : "Bonjour JULIEN !"
```

## 🏗️ Architecture

Vision dispose d'une architecture modulaire avec séparation claire des responsabilités :

```
Vision (Orchestrateur)
├── Parser          - Tokenization & construction AST
├── Compiler        - Compilation AST vers PHP
├── CacheManager    - Cache multi-niveaux (parsed + compiled)
├── FilterManager   - Registre et application des filtres
├── VariableResolver - Résolution des variables imbriquées
└── ControlStructureProcessor - Gestion For/If
```

**Deux Pipelines de Rendu :**

1. **Pipeline Legacy** : Template → Parse → Rendu → Sortie
2. **Pipeline Compilé** : Template → Parse → Compile → Cache → Exécute (95%+ plus rapide en cache)

## 📋 Référence des Fonctionnalités

- ✅ **Variables** - `{{ variable }}` avec échappement automatique
- ✅ **Accès Imbriqué** - `{{ user.profile.name }}` pour propriétés profondes
- ✅ **Filtres** - Syntaxe pipe `{{ name|upper|trim }}` avec 10+ filtres intégrés
- ✅ **Structures de Contrôle** - `{% if %}`, `{% else %}`, boucles `{% for %}`
- ✅ **Opérateurs de Comparaison** - `==`, `!=`, `>`, `<`, `>=`, `<=`
- ✅ **Variables de Boucle** - `loop.index`, `loop.first`, `loop.last`
- ✅ **Support Objets** - Getters, propriétés publiques, méthodes magiques
- ✅ **Filtres Personnalisés** - Création facile via interface
- ✅ **Fonctions Personnalisées** - Enregistrement de fonctions callable
- ✅ **Protection XSS** - Échappement automatique activé par défaut
- ✅ **Cache Intelligent** - Multi-niveaux avec TTL et invalidation automatique
- ✅ **Cache de Fragments** - Cache les composants par props pour gains massifs
- ✅ **Constant Folding** - Pré-calcule les expressions constantes à la compilation (10-20% plus rapide)
- ✅ **Dead Branch Elimination** - Supprime le code inaccessible ({% if false %}) à la compilation (5-10% plus rapide)
- ✅ **Inline Filters** - Compile les filtres communs en PHP natif (15-30% plus rapide)
- ✅ **Compilation** - Compilation PHP optionnelle pour performances extrêmes
- ✅ **Outils CLI** - Gestion du cache, compilation et commandes statistiques

## 📖 Documentation

### Variables

Les variables sont affichées avec la syntaxe `{{ variable }}` :

```php
$template = 'Bonjour {{ name }} !';
$html = $vision->renderString($template, ['name' => 'Julien']);
// Résultat: "Bonjour Julien !"
```

### Extensions de fichiers supportées

Lorsque vous appelez `render('template')` sans extension, Vision essaiera dans cet ordre :

1. `.html.vis` (recommandé pour les templates Vision)
2. `.vis`
3. `.php`
4. `.html`

### Includes simples (partiels)

Vision fournit des fonctions intégrées pour inclure d'autres templates :

```php
// Rendre un partiel avec des variables explicites
{{ template("partials/header", headerData) }}

// Alias
{{ include("partials/footer", footerData) }}
```

Note : vous devez passer les variables explicitement (ex. `headerData`), car Vision ne capture pas implicitement le scope parent pour les includes.

### Extensions de fichiers supportées

Lorsque vous appelez `render('template')` sans extension, Vision essaiera dans cet ordre :

1. `.html.vis` (recommandé pour les templates Vision)
2. `.vis`
3. `.php`
4. `.html`

Exemples :

```php
// Charge automatiquement templates/welcome.html.vis si présent
$vision->render('welcome');

// Charge explicitement un fichier .vis
$vision->render('email/welcome.vis');
```

### Variables imbriquées

Vous pouvez accéder aux propriétés imbriquées avec la notation point :

```php
$template = '{{ user.firstname }} {{ user.lastname }}';
$html = $vision->renderString($template, [
    'user' => [
        'firstname' => 'Julien',
        'lastname' => 'Linard',
    ],
]);
// Résultat: "Julien Linard"
```

### Filtres

Les filtres permettent de transformer les variables. Utilisez le pipe `|` pour chaîner plusieurs filtres :

```php
$template = '{{ name|upper|trim }}';
$html = $vision->renderString($template, ['name' => '  julien  ']);
// Résultat: "JULIEN"
```

#### Filtres disponibles

##### upper
Convertit en majuscules.

```php
{{ name|upper }}
```

##### lower
Convertit en minuscules.

```php
{{ name|lower }}
```

##### trim
Supprime les espaces en début et fin.

```php
{{ name|trim }}
```

##### escape
Échappe les caractères HTML (protection XSS).

```php
{{ content|escape }}
```

##### default
Fournit une valeur par défaut si la variable est vide.

```php
{{ name|default:"Anonyme" }}
```

##### date
Formate une date.

```php
{{ date|date:"Y-m-d" }}
{{ date|date:"d/m/Y H:i" }}
```

##### number
Formate un nombre.

```php
{{ price|number:2 }}           // 2 décimales
{{ price|number:2:".":"," }}    // Format personnalisé
```

##### length
Retourne la longueur d'une chaîne ou d'un tableau.

```php
{{ name|length }}
{{ items|length }}
```

##### json
Encode une valeur en JSON.

```php
{{ data|json }}
```

### Structures de contrôle

#### Conditions {% if %}

```php
$template = <<<'TEMPLATE'
{% if isActive %}
    <p>Compte actif</p>
{% else %}
    <p>Compte inactif</p>
{% endif %}
TEMPLATE;

$html = $vision->renderString($template, ['isActive' => true]);
```

#### Opérateurs de comparaison

```php
{% if age >= 18 %}
    <p>Majeur</p>
{% endif %}

{% if status == "active" %}
    <p>Actif</p>
{% endif %}
```

#### Boucles {% for %}

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

#### Variables de boucle

Dans une boucle, vous avez accès à la variable `loop` :

- `loop.index` : Index actuel (commence à 1)
- `loop.index0` : Index actuel (commence à 0)
- `loop.first` : `true` si c'est la première itération
- `loop.last` : `true` si c'est la dernière itération
- `loop.length` : Nombre total d'éléments

```php
{% for item in items %}
    {{ loop.index }}: {{ item }}
    {% if loop.first %}Premier élément{% endif %}
    {% if loop.last %}Dernier élément{% endif %}
{% endfor %}
```

### Échappement automatique

Par défaut, Vision échappe automatiquement toutes les variables pour protéger contre les attaques XSS :

```php
$vision = new Vision('', true); // Échappement activé (par défaut)

$template = '{{ content }}';
$html = $vision->renderString($template, [
    'content' => '<script>alert("xss")</script>'
]);
// Résultat: "&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;"
```

Pour désactiver l'échappement automatique :

```php
$vision = new Vision('', false);
// Ou
$vision->setAutoEscape(false);
```

### Filtres personnalisés

Vous pouvez créer vos propres filtres :

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
// Résultat: "neiluJ"
```

### Fonctions personnalisées

Vous pouvez enregistrer des fonctions personnalisées :

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
// Résultat: "HELLO - ab"
```

## 📚 Référence API

### Vision

#### `__construct(string $templateDir = '', bool $autoEscape = true)`

Crée une nouvelle instance de Vision.

```php
$vision = new Vision('/chemin/vers/templates', true);
```

#### `render(string $template, array $variables = []): string`

Rend un template depuis un fichier.

```php
$html = $vision->render('welcome', ['name' => 'Julien']);
```

#### `renderString(string $content, array $variables = []): string`

Rend directement une chaîne de template.

```php
$html = $vision->renderString('{{ name }}', ['name' => 'Julien']);
```

#### `registerFilter(FilterInterface $filter): self`

Enregistre un filtre personnalisé.

```php
$vision->registerFilter(new CustomFilter());
```

#### `registerFunction(string $name, callable $callback): self`

Enregistre une fonction personnalisée.

```php
$vision->registerFunction('custom', function ($arg) {
    return strtoupper($arg);
});
```

#### `setAutoEscape(bool $enabled): self`

Active ou désactive l'échappement automatique.

```php
$vision->setAutoEscape(false);
```

#### `setCache(bool $enabled, ?string $cacheDir = null): self`

Active ou désactive le cache (à venir).

```php
$vision->setCache(true, '/chemin/cache');
```

## 🔒 Sécurité

Vision est conçu avec la sécurité comme priorité absolue :

- ✅ **Échappement automatique** - Toutes les variables échappées par défaut (protection XSS)
- ✅ **Protection Path Traversal** - Validation stricte des chemins avec `realpath()`
- ✅ **Validation des Fonctions** - Seuls les noms de fonctions autorisés
- ✅ **Prévention Injection Objet** - Sérialisation sécurisée des objets en cache
- ✅ **Protection ReDoS** - Patterns regex avec quantificateurs limités
- ✅ **Sécurité Concurrence** - Verrouillage fichiers avec timeout sur opérations cache

```php
// L'échappement automatique est activé par défaut
$vision = new Vision('', true);

$html = $vision->renderString('{{ content }}', [
    'content' => '<script>alert("xss")</script>'
]);
// Sortie : "&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;"
```

### Bonnes Pratiques de Sécurité

1. **Conserver l'échappement automatique** activé en production
2. **Utiliser le filtre `escape`** explicitement pour les sorties critiques
3. **Valider toutes les entrées** utilisateur avant passage aux templates
4. **Activer le cache** pour réduire la charge de parsing
5. **Utiliser le pipeline compilé** pour sécurité additionnelle via génération de code

## 🎯 Exemple Complet

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
    <h1>Bienvenue {{ user.name|trim }} !</h1>
    
    {% if user.isActive %}
        <p>Votre compte est actif.</p>
    {% else %}
        <p>Votre compte est inactif.</p>
    {% endif %}
    
    {% if posts %}
        <h2>Articles ({{ posts|length }})</h2>
        <ul>
        {% for post in posts %}
            <li>
                <strong>{{ post.title }}</strong>
                <small>{{ post.date|date:"d/m/Y" }}</small>
            </li>
        {% endfor %}
        </ul>
    {% else %}
        <p>Aucun article disponible.</p>
    {% endif %}
</body>
</html>
TEMPLATE;

$html = $vision->renderString($template, [
    'title' => 'Mon Site',
    'user' => [
        'name' => '  Julien Linard  ',
        'isActive' => true,
    ],
    'posts' => [
        [
            'title' => 'Premier article',
            'date' => '2025-01-15',
        ],
        [
            'title' => 'Deuxième article',
            'date' => '2025-01-20',
        ],
    ],
]);

echo $html;
```

## 🚀 Utilisation Avancée

### Filtres Personnalisés

Créez des filtres puissants en implémentant `FilterInterface` :

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

// Utilisation : {{ title|slugify }}
// "Hello World!" devient "hello-world"
```

### Fonctions Personnalisées

Enregistrez des fonctions personnalisées pour la logique de template :

```php
$vision->registerFunction('asset', function ($path) {
    return '/assets/' . ltrim($path, '/');
});

$vision->registerFunction('trans', function ($key, $params = []) {
    // Votre logique de traduction ici
    return __($key, $params);
});

// Utilisation : {{ asset("css/style.css") }}
// Utilisation : {{ trans("welcome.message") }}
```

### Configuration Production

Configuration recommandée pour environnements de production :

```php
<?php
// config/template.php

use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;

$vision = new Vision(
    templateDir: __DIR__ . '/../templates',
    autoEscape: true  // Protection XSS
);

// Activer le cache de rendu (TTL 24h)
$vision->setCache(
    enabled: true,
    cacheDir: __DIR__ . '/../var/cache/templates',
    ttl: 86400
);

// Activer le pipeline de compilation pour performances maximales
if (getenv('APP_ENV') === 'production') {
    $vision->setParser(new TemplateParser());
    $vision->setCompiler(new TemplateCompiler());
    $vision->setCacheManager(new CacheManager(
        cacheDir: __DIR__ . '/../var/cache/compiled',
        ttl: 604800  // 7 jours pour templates compilés
    ));
}

// Enregistrer vos filtres et fonctions personnalisés
$vision->registerFilter(new SlugifyFilter());
$vision->registerFunction('asset', fn($path) => '/assets/' . $path);

return $vision;
```

### Gestion du Cache

```php
// Nettoyer les entrées de cache expirées
$deletedFiles = $vision->clearCache(); // Utilise le TTL par défaut
$deletedFiles = $vision->clearCache(3600); // Nettoyer entrées > 1 heure
$deletedFiles = $vision->clearCache(0); // Tout nettoyer

// Pour le cache compilé (CacheManager)
$stats = $cacheManager->getStats();
// Retourne : ['total' => 42, 'size' => 1048576, 'oldest' => 1638316800]

$cacheManager->clearAll(); // Nettoyer tous les templates compilés
$cacheManager->clear(3600); // Nettoyer templates compilés > 1 heure
```

## 🧪 Tests

Vision est livré avec une couverture de tests complète :

```bash
# Lancer tous les tests
composer test

# Lancer avec couverture
composer test:coverage

# Lancer une suite de tests spécifique
./vendor/bin/phpunit tests/SecurityTest.php
```

**Statistiques des Tests :**
- 116 tests répartis sur 13 suites de tests
- 261 assertions
- Couverture fonctionnelle 100%
- Tests pour : Sécurité, Performance, Cache, Filtres, Boucles, Conditions, Objets, Parser, Compiler

## 📚 Référence API

### Classe Vision

#### Constructeur

```php
public function __construct(
    string $templateDir = '',
    bool $autoEscape = true
)
```

#### Méthodes

##### `render(string $template, array $variables = []): string`

Rend un fichier template avec les variables données.

```php
$html = $vision->render('page/home', [
    'title' => 'Bienvenue',
    'user' => $currentUser
]);
```

##### `renderString(string $content, array $variables = [], int $depth = 0): string`

Rend directement une chaîne de template.

```php
$html = $vision->renderString('Bonjour {{ name }} !', ['name' => 'Monde']);
```

##### `registerFilter(FilterInterface $filter): self`

Enregistre un filtre personnalisé.

```php
$vision->registerFilter(new CustomFilter());
```

##### `registerFunction(string $name, callable $callback): self`

Enregistre une fonction personnalisée.

```php
$vision->registerFunction('url', fn($path) => "https://example.com/$path");
```

##### `setAutoEscape(bool $enabled): self`

Contrôle l'échappement HTML automatique.

```php
$vision->setAutoEscape(false); // Désactiver (non recommandé)
```

##### `setCache(bool $enabled, ?string $cacheDir = null, int $ttl = 3600): self`

Configure le cache de rendu des templates.

```php
$vision->setCache(true, '/tmp/cache', 3600); // TTL 1 heure
```

##### `clearCache(?int $maxAge = null): int`

Nettoie les templates en cache et retourne le nombre de fichiers supprimés.

```php
$count = $vision->clearCache(3600); // Nettoyer cache > 1 heure
```

##### `setParser(TemplateParser $parser): self`

Active le sous-système de parsing (requis pour compilation).

```php
$vision->setParser(new TemplateParser());
```

##### `setCompiler(TemplateCompiler $compiler): self`

Active le sous-système de compilation.

```php
$vision->setCompiler(new TemplateCompiler());
```

##### `setCacheManager(CacheManager $cacheManager): self`

Active le gestionnaire de cache pour templates compilés.

```php
$vision->setCacheManager(new CacheManager('/tmp/compiled', 86400));
```

### Filtres Intégrés

| Filtre | Description | Exemple |
|--------|-------------|---------|
| `upper` | Convertit en majuscules | `{{ name\|upper }}` |
| `lower` | Convertit en minuscules | `{{ name\|lower }}` |
| `trim` | Supprime les espaces | `{{ text\|trim }}` |
| `escape` | Échappe les entités HTML | `{{ html\|escape }}` |
| `default` | Fournit une valeur par défaut | `{{ name\|default:"Invité" }}` |
| `date` | Formate les dates | `{{ date\|date:"d/m/Y" }}` |
| `number` | Formate les nombres | `{{ price\|number:2 }}` |
| `length` | Retourne la longueur | `{{ items\|length }}` |
| `json` | Encode en JSON | `{{ data\|json }}` |

### Variables de Boucle

Disponibles dans les boucles `{% for %}` :

| Variable | Type | Description |
|----------|------|-------------|
| `loop.index` | int | Itération actuelle (commence à 1) |
| `loop.index0` | int | Itération actuelle (commence à 0) |
| `loop.first` | bool | Vrai à la première itération |
| `loop.last` | bool | Vrai à la dernière itération |
| `loop.length` | int | Nombre total d'éléments |

## 💡 Conseils de Performance

1. **Activer le Pipeline de Compilation** - Utiliser Parser + Compiler + CacheManager pour amélioration de 95%+
2. **Utiliser un TTL Approprié** - TTL long pour templates stables, court pour templates changeants
3. **Minimiser les Boucles Imbriquées** - Garder profondeur de récursion < 20 pour performances optimales
4. **Mettre en Cache les Objets Template** - Réutiliser l'instance Vision entre requêtes (pattern singleton)
5. **Pré-compiler en Production** - Préchauffer le cache après déploiement

### Benchmarks

Mesures de performance réelles (PHP 8.5.0) :

| Opération | Temps | Mémoire |
|-----------|-------|---------|
| Template simple (legacy) | 1,4ms | 350KB |
| Template complexe (legacy) | 17ms | 1,2MB |
| 1000 itérations boucle | 120ms | 2,5MB |
| **Compilé (cache hit)** | **0,5ms** | **200KB** |
| **Compilé (premier rendu)** | **20ms** | **1,5MB** |

## 🔧 Options de Configuration

### Variables d'Environnement

```bash
# Exemple .env
VISION_CACHE_ENABLED=true
VISION_CACHE_DIR=/var/cache/vision
VISION_CACHE_TTL=3600
VISION_AUTO_ESCAPE=true
VISION_COMPILED_ENABLED=true
```

### Intégration Framework

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

## 🐛 Dépannage

### Problèmes Courants

**Cache non fonctionnel**
```php
// S'assurer que le répertoire cache est accessible en écriture
chmod 775 var/cache/templates
```

**Templates introuvables**
```php
// Vérifier le chemin du répertoire des templates
$vision = new Vision(realpath(__DIR__ . '/templates'));
```

**Erreurs de compilation**
```php
// Nettoyer le cache compilé
$cacheManager->clearAll();
```

**Problèmes de performance**
```php
// Activer le pipeline de compilation
$vision->setParser(new TemplateParser());
$vision->setCompiler(new TemplateCompiler());
$vision->setCacheManager(new CacheManager('/tmp/compiled'));
```

## 📖 Guide de Migration

### Depuis Twig

Vision utilise une syntaxe similaire à Twig pour faciliter la migration :

| Twig | Vision | Notes |
|------|--------|-------|
| `{{ var }}` | `{{ var }}` | ✅ Identique |
| `{{ var\|upper }}` | `{{ var\|upper }}` | ✅ Identique |
| `{% if var %}` | `{% if var %}` | ✅ Identique |
| `{% for item in items %}` | `{% for item in items %}` | ✅ Identique |
| `{% extends 'base' %}` | ❌ Non supporté | Utiliser includes |
| `{% block name %}` | ❌ Non supporté | Utiliser partiels |
| `{% include 'partial' %}` | ⚠️ Bientôt disponible | Fonctionnalité roadmap |

## 🗺️ Feuille de Route

- [ ] Héritage de templates (`{% extends %}`, `{% block %}`)
- [ ] Système include/import (`{% include %}`)
- [ ] Support des macros (`{% macro %}`)
- [ ] Contrôle d'espacement (`{%-` et `-%}`)
- [ ] Opérateur ternaire (`{{ var ? 'oui' : 'non' }}`)
- [ ] Filtres tableau/objet (`{{ items|first }}`, `{{ items|last }}`)
- [ ] Opérations mathématiques dans templates (`{{ price * 1.2 }}`)
- [ ] Interpolation de chaînes (`{{ "Bonjour #{name}" }}`)

## 📝 Licence

Licence MIT - voir le fichier [LICENSE](LICENSE) pour détails.

## 🤝 Contribuer

Les contributions sont les bienvenues ! Veuillez lire nos [directives de contribution](CONTRIBUTING.md) avant de soumettre des PRs.

### Configuration Développement

```bash
# Cloner le dépôt
git clone https://github.com/julienlinard/php-vision.git
cd php-vision

# Installer les dépendances
composer install

# Lancer les tests
composer test

# Vérifier le style de code
composer cs

# Analyse statique
composer analyze
```

## 💝 Soutenir ce Projet

Si vous trouvez PHP Vision utile, considérez soutenir son développement :

- ⭐ Étoiler le dépôt
- 🐛 Signaler des bugs et suggérer des fonctionnalités
- 💻 Contribuer des améliorations de code
- 📖 Améliorer la documentation
- 💰 [Devenir un sponsor](https://github.com/sponsors/julien-lin)

## 🙏 Remerciements

- Inspiré par Twig, Blade et Smarty
- Construit avec les fonctionnalités modernes de PHP 8.0+
- Testé par la communauté et éprouvé en production

---

**Développé avec ❤️ par [Julien Linard](https://github.com/julien-lin)**

Pour questions, problèmes ou demandes de fonctionnalités, veuillez [ouvrir une issue](https://github.com/julienlinard/php-vision/issues) sur GitHub.
