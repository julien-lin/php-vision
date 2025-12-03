# Template Inheritance - Vision

## Vue d'ensemble

Vision supporte maintenant l'héritage de templates (Template Inheritance) inspiré de Twig, permettant de créer des layouts réutilisables et d'étendre des templates parents.

### Caractéristiques

- ✅ **`{% extends %}`** : Hériter d'un template parent
- ✅ **`{% block %}`** : Définir des zones remplaçables
- ✅ **`{{ parent() }}`** : Référencer le contenu du block parent
- ✅ **Héritage multi-niveaux** : Support de chaînes d'héritage illimitées
- ✅ **Blocks imbriqués** : Les blocks peuvent contenir d'autres blocks
- ✅ **Détection de cycles** : Protection contre l'héritage circulaire
- ✅ **Résolution compile-time** : Zero overhead runtime après cache

## Performance

- **Compile-time resolution** : L'héritage est résolu à la compilation (pas au runtime comme Blade)
- **~2-3ms** pour une chaîne d'héritage de 3 niveaux
- **0ms runtime overhead** après mise en cache
- Compatible avec toutes les optimisations du compilateur Vision (Dead Branch Elimination, Constant Folding, etc.)

## Syntaxe

### 1. Extends - Hériter d'un template

Le template enfant hérite du parent avec `{% extends %}`. Cette directive **doit être la première** du template.

```twig
{% extends "base.html" %}
```

### 2. Block - Définir une zone remplaçable

Les blocks marquent les zones que les templates enfants peuvent override.

**Template parent** (`base.html`):
```html
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}Default Title{% endblock %}</title>
</head>
<body>
    <header>{% block header %}Default Header{% endblock %}</header>
    <main>{% block content %}Default Content{% endblock %}</main>
    <footer>{% block footer %}Default Footer{% endblock %}</footer>
</body>
</html>
```

**Template enfant** (`page.html`):
```twig
{% extends "base.html" %}

{% block title %}My Custom Page{% endblock %}

{% block content %}
    <h1>Welcome!</h1>
    <p>This is my custom content.</p>
{% endblock %}
```

**Résultat** :
```html
<!DOCTYPE html>
<html>
<head>
    <title>My Custom Page</title>
</head>
<body>
    <header>Default Header</header>
    <main>
        <h1>Welcome!</h1>
        <p>This is my custom content.</p>
    </main>
    <footer>Default Footer</footer>
</body>
</html>
```

### 3. Parent - Référencer le contenu parent

Utilisez `{{ parent() }}` pour injecter le contenu du block parent dans le block enfant.

**Template parent** (`base.html`):
```html
{% block styles %}
    <link rel="stylesheet" href="/css/base.css">
{% endblock %}
```

**Template enfant** (`page.html`):
```twig
{% extends "base.html" %}

{% block styles %}
    {{ parent() }}
    <link rel="stylesheet" href="/css/custom.css">
{% endblock %}
```

**Résultat** :
```html
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/custom.css">
```

## Exemples avancés

### Héritage multi-niveaux

Créez des chaînes d'héritage pour organiser vos layouts :

**Grand-parent** (`base.html`):
```html
<!DOCTYPE html>
<html>
<body>
    {% block content %}Base content{% endblock %}
</body>
</html>
```

**Parent** (`layout.html`):
```twig
{% extends "base.html" %}

{% block content %}
    <div class="container">
        {% block page %}Page content{% endblock %}
    </div>
{% endblock %}
```

**Enfant** (`article.html`):
```twig
{% extends "layout.html" %}

{% block page %}
    <article>
        <h1>{{ title }}</h1>
        <p>{{ content }}</p>
    </article>
{% endblock %}
```

### Blocks imbriqués

Les blocks peuvent contenir d'autres blocks :

**Parent** (`base.html`):
```html
{% block outer %}
    <div class="wrapper">
        {% block inner %}Inner content{% endblock %}
    </div>
{% endblock %}
```

**Enfant** (`child.html`):
```twig
{% extends "base.html" %}

{% block inner %}Custom inner content{% endblock %}
```

Le block `outer` est conservé du parent, seul `inner` est remplacé.

### Blocks avec variables et boucles

Les blocks peuvent contenir toute la syntaxe Vision :

**Parent** (`base.html`):
```html
{% block items %}
    {% for item in items %}
        <li>Default: {{ item }}</li>
    {% endfor %}
{% endblock %}
```

**Enfant** (`custom.html`):
```twig
{% extends "base.html" %}

{% block items %}
    {% for item in items %}
        <li class="custom">{{ item | upper }}</li>
    {% endfor %}
{% endblock %}
```

### {{ parent() }} multiple

Vous pouvez appeler `{{ parent() }}` plusieurs fois :

```twig
{% extends "base.html" %}

{% block content %}
    <div class="before">{{ parent() }}</div>
    <div class="after">{{ parent() }}</div>
{% endblock %}
```

### Blocks avec conditions

```twig
{% extends "base.html" %}

{% block content %}
    {% if premium %}
        <div class="premium-content">Premium features</div>
    {% else %}
        {{ parent() }}
    {% endif %}
{% endblock %}
```

## Configuration

Pour utiliser le Template Inheritance, votre instance Vision doit être configurée avec le pipeline compilé complet :

```php
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Parser\TemplateParser;
use JulienLinard\Vision\Compiler\TemplateCompiler;
use JulienLinard\Vision\Cache\CacheManager;

$vision = new Vision(__DIR__ . '/templates');

// Configurer le pipeline compilé
$parser = new TemplateParser();
$compiler = new TemplateCompiler();
$cacheManager = new CacheManager(__DIR__ . '/cache');

$vision->setParser($parser);
$vision->setCompiler($compiler);
$vision->setCacheManager($cacheManager);
$vision->setCache(true, __DIR__ . '/cache');

// Maintenant vous pouvez utiliser {% extends %}
$html = $vision->render('page.html', ['title' => 'My Page']);
```

**Important** : L'InheritanceResolver est automatiquement configuré lorsque vous appelez `setCompiler()` après `setParser()`.

## Restrictions et bonnes pratiques

### ✅ Bonnes pratiques

1. **`{% extends %}` en premier** : Placez toujours `{% extends %}` au début du fichier (seuls commentaires/espaces autorisés avant)

2. **Nommer les blocks clairement** : Utilisez des noms descriptifs (`{% block sidebar %}`, `{% block hero %}`)

3. **Structure cohérente** : Gardez les mêmes noms de blocks dans toute la hiérarchie

4. **Layouts simples** : Ne multipliez pas les niveaux d'héritage inutilement (2-3 niveaux suffisent généralement)

### ❌ Limitations actuelles

1. **Pas de filtres sur `parent()`** : `{{ parent() | upper }}` n'est pas supporté

   **Solution** : Appliquez les filtres au contenu, pas à parent()
   
   ```twig
   {% block title %}{{ title | upper }}{% endblock %}
   ```

2. **Blocks dynamiques** : Le nom du block doit être statique, pas une variable

   ```twig
   {# ❌ Ne fonctionne pas #}
   {% block {{ blockName }} %}{% endblock %}
   
   {# ✅ Correct #}
   {% block content %}{% endblock %}
   ```

## Détection d'erreurs

### Héritage circulaire

Vision détecte automatiquement les cycles :

```php
// a.html: {% extends "b.html" %}
// b.html: {% extends "a.html" %}

$vision->render('a.html'); 
// Throws: VisionException "Circular inheritance detected: a.html -> b.html -> a.html"
```

### Template parent introuvable

```php
// child.html: {% extends "nonexistent.html" %}

$vision->render('child.html');
// Throws: TemplateNotFoundException "Template not found: nonexistent.html"
```

## Comparaison avec Twig/Blade

| Fonctionnalité | Vision | Twig | Blade |
|---------------|--------|------|-------|
| `{% extends %}` | ✅ | ✅ | ✅ (@extends) |
| `{% block %}` | ✅ | ✅ | ✅ (@section) |
| `{{ parent() }}` | ✅ | ✅ | ✅ (@parent) |
| Héritage multi-niveaux | ✅ | ✅ | ✅ |
| Blocks imbriqués | ✅ | ✅ | ✅ |
| Résolution compile-time | ✅ | ✅ | ❌ (runtime) |
| Détection de cycles | ✅ | ✅ | ⚠️ |
| Filtres sur parent() | ❌ | ❌ | ❌ |

**Avantage Vision** : Résolution à la compilation comme Twig 3.x, offrant de meilleures performances que Blade qui résout au runtime.

## Architecture interne

Le système d'héritage Vision est implémenté en 3 composants :

1. **TemplateParser** : Détecte les tokens `{% extends %}`, `{% block %}`, `{{ parent() }}`

2. **InheritanceResolver** : Résout l'héritage à la compilation
   - Charge récursivement les templates parents
   - Extrait tous les blocks (y compris imbriqués)
   - Fusionne les blocks enfants dans l'AST parent
   - Remplace `{{ parent() }}` par le contenu parent
   - Détecte les cycles d'héritage

3. **TemplateCompiler** : Intègre la résolution dans le pipeline
   - Parse → **Résoudre héritage** → Optimiser → Compiler

```
Template enfant
     ↓
Parser → AST avec EXTENDS/BLOCK/PARENT nodes
     ↓
InheritanceResolver:
  1. Détecter {% extends %}
  2. Charger parent (récursif)
  3. Extraire blocks enfant
  4. Remplacer blocks dans parent
  5. Résoudre {{ parent() }}
     ↓
AST aplati (sans EXTENDS/BLOCK/PARENT)
     ↓
Optimiseurs (Dead Branch, Constant Folding, etc.)
     ↓
Compiler → Code PHP exécutable
     ↓
Cache
```

**Performance** : L'héritage ajoute ~2-3ms au temps de compilation initial, mais 0ms au runtime grâce au cache.

## Tests

Le système d'héritage est couvert par 21 tests complets :

- Extends simple
- Blocks multiples
- Héritage multi-niveaux (3+ niveaux)
- `{{ parent() }}` simple et multiple
- Blocks avec variables, boucles, conditions
- Blocks imbriqués
- Détection de cycles
- Templates parents introuvables
- Blocks avec filtres
- HTML complexe

Voir `tests/InheritanceTest.php` pour les exemples complets.

## Prochaines évolutions

Fonctionnalités planifiées :

- **Horizontal reuse** : `{% use "blocks.html" %}` pour réutiliser des blocks sans extends
- **Block shortcuts** : `{% block title "Mon titre" %}` (syntaxe courte)
- **Named endblock** : `{% endblock title %}` pour clarté
- **Dynamic blocks** : Permettre `{% block var_name %}` avec variable

---

**Félicitations !** Vision dispose maintenant d'un système d'héritage de templates complet, performant et simple à utiliser. 🎉
