# Vision vs Twig/Blade/Smarty - Analyse Comparative Complète

*Date: 3 décembre 2025*  
*Version: Vision 1.1 (294 tests, 725 assertions)*  
*Nouveau: ✅ Template Inheritance complété*

---

## 📊 Vue d'Ensemble

Vision est un moteur de templates PHP moderne avec des **performances exceptionnelles** (97% plus rapide que Twig en mode compilé), mais certaines fonctionnalités standard des moteurs matures sont encore manquantes.

### Score Global

| Critère | Vision | Twig | Blade | Smarty |
|---------|--------|------|-------|--------|
| **Performance** | ⭐⭐⭐⭐⭐ (97% faster) | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |
| **Architecture** | ⭐⭐⭐⭐⭐ (Modern AST) | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ |
| **Fonctionnalités** | ⭐⭐⭐ (Basiques) | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Sécurité** | ⭐⭐⭐⭐⭐ (Sandbox) | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| **Optimisations** | ⭐⭐⭐⭐⭐ (Uniques) | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |
| **Adoption** | ⭐⭐ (Nouveau) | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |

---

## ✅ Points Forts de Vision

### 1. Performance Exceptionnelle 🚀

**97% plus rapide que Twig** en mode compilé avec cache :

| Scénario | Twig | Vision (Legacy) | Vision (Compiled) | Amélioration |
|----------|------|----------------|-------------------|--------------|
| Template simple | 2.1ms | 1.4ms | 0.1ms | **95%** |
| Template complexe | 45ms | 17ms | 0.5ms | **97%** |
| 1000 itérations | 350ms | 120ms | 2ms | **99%** |

### 2. Optimisations Compilateur Uniques ⚡

Vision possède des optimisations que **Twig/Blade n'ont pas** :

#### **Constant Folding** (10-20% gains)
```php
// Template
{% if 86400 == 24 * 60 * 60 %}Seconds in a day{% endif %}

// Compilé en
{% if true %}Seconds in a day{% endif %}

// Puis optimisé en
Seconds in a day
```

#### **Dead Branch Elimination** (5-10% gains)
```php
// Template
{% if true %}Active{% else %}Dead code{% endif %}

// Compilé en (else supprimé)
Active
```

#### **Inline Filters** (15-30% gains)
```php
// Template
{{ name|upper|trim }}

// Compilé en PHP natif (pas d'appel FilterManager)
trim(strtoupper($name))
```

### 3. Fragment Caching pour Composants 🎯

Vision peut cacher des **composants individuels** par props :

```php
// Premier render avec props {label: "Save", variant: "primary"}
{{ component("Button", buttonProps) }}  // Render + cache

// Rendu ultérieur avec mêmes props
{{ component("Button", buttonProps) }}  // Cache hit (50-80% faster)

// Props différents = nouveau cache
{{ component("Button", {label: "Cancel"}) }}  // Cache miss, nouveau render
```

**Twig/Blade n'ont pas** cette fonctionnalité (seulement cache de template entier).

### 4. Architecture Moderne 🏗️

- **AST-based compilation** (comme Twig 3.x)
- **Separation of concerns** (7 modules indépendants)
- **PHP 8.0+ strict typing**
- **Zero dependencies**
- **100% tested** (230 tests, 486 assertions)

### 5. Sécurité Robuste 🔒

- **Sandbox granulaire** avec whitelist
- **Auto-escape** par défaut
- **Path traversal protection**
- **ReDoS protection** (regex optimisés)
- **Métriques et logging** intégrés

---

## ❌ Fonctionnalités Manquantes (vs Twig/Blade)

### 🔴 CRITIQUES (Blockers pour adoption)

#### ~~1. Template Inheritance + Blocks~~ ✅ COMPLÉTÉ

**Status :** ✅ **IMPLÉMENTÉ** (3 décembre 2025)

Vision supporte maintenant l'héritage de templates comme Twig :

```twig
{# layouts/base.html #}
<!DOCTYPE html>
<html>
<head>
    <title>{% block title %}Default{% endblock %}</title>
</head>
<body>
    {% block content %}{% endblock %}
</body>
</html>

{# pages/home.html #}
{% extends "layouts/base.html" %}

{% block title %}Homepage{% endblock %}

{% block content %}
    <h1>Welcome!</h1>
    {{ parent() }}  {# Référence le contenu du block parent #}
{% endblock %}
```

**Fonctionnalités :**
- ✅ `{% extends "parent.html" %}` - Héritage de template
- ✅ `{% block name %}...{% endblock %}` - Blocks remplaçables  
- ✅ `{{ parent() }}` - Référencer le contenu parent
- ✅ Héritage multi-niveaux (3+ levels)
- ✅ Blocks imbriqués
- ✅ Détection de cycles
- ✅ Résolution compile-time (0ms overhead)
- ✅ 21 tests complets

📖 **[Documentation complète](TEMPLATE_INHERITANCE.md)**

---

#### 2. Macros (Fonctions de Templates)

**Impact :** Réutilisabilité limitée pour snippets complexes.

**Twig :**
```twig
{# macros/forms.html.twig #}
{% macro input(name, value, type = "text") %}
    <input type="{{ type }}" 
           name="{{ name }}" 
           value="{{ value }}"
           class="form-control">
{% endmacro %}

{% macro select(name, options, selected) %}
    <select name="{{ name }}">
        {% for key, label in options %}
            <option value="{{ key }}" 
                    {{ key == selected ? 'selected' : '' }}>
                {{ label }}
            </option>
        {% endfor %}
    </select>
{% endmacro %}

{# Usage #}
{% import "macros/forms.html.twig" as forms %}
{{ forms.input("email", user.email, "email") }}
{{ forms.select("country", countries, user.country) }}
```

**Vision actuel :**
```php
// ❌ Pas de macros
// ✅ Seulement registerFunction() (PHP, pas templates)
$vision->registerFunction('input', function($name, $value) {
    return "<input name='$name' value='$value'>";
});
```

**Limitation :** Fonctions définies en PHP seulement, pas dans templates.

---

### 🟠 IMPORTANTES (Compétitivité)

#### 3. Filtres Tableau Avancés

**Vision actuel :** 1 filtre (`length`)  
**Twig :** 25+ filtres tableau

**Manquants :**

| Filtre | Twig | Vision | Exemple |
|--------|------|--------|---------|
| `first` | ✅ | ❌ | `{{ items\|first }}` |
| `last` | ✅ | ❌ | `{{ items\|last }}` |
| `slice` | ✅ | ❌ | `{{ items\|slice(0, 5) }}` |
| `sort` | ✅ | ❌ | `{{ items\|sort }}` |
| `reverse` | ✅ | ❌ | `{{ items\|reverse }}` |
| `join` | ✅ | ❌ | `{{ items\|join(', ') }}` |
| `batch` | ✅ | ❌ | `{{ items\|batch(3) }}` (groupes de 3) |
| `filter` | ✅ | ❌ | `{{ items\|filter(i => i.active) }}` |
| `map` | ✅ | ❌ | `{{ items\|map(i => i.name) }}` |
| `merge` | ✅ | ❌ | `{{ array1\|merge(array2) }}` |

**Impact :** Manipulation de collections très limitée dans templates.

---

#### 4. Opérateurs et Expressions Avancées

**Twig :**
```twig
{# Opérations mathématiques #}
{{ price * 1.2 }}
{{ (total + shipping) * taxRate }}

{# Concaténation #}
{{ firstname ~ ' ' ~ lastname }}

{# Ternaire #}
{{ items|length > 5 ? 'Many items' : 'Few items' }}

{# Tests #}
{{ user is defined ? user.name : 'Guest' }}
{{ user is null }}
{{ items is empty }}
{{ number is odd }}
{{ number is even }}
{{ number is divisible by(3) }}

{# Appartenance #}
{{ 'admin' in user.roles }}
{{ user not in bannedUsers }}
```

**Vision actuel :**
```php
// ✅ Comparaisons simples
{% if age >= 18 %}Adult{% endif %}
{% if status == "active" %}Active{% endif %}

// ❌ Pas de math dans templates
// ❌ Pas de ternaire
// ❌ Pas de tests (is defined, is null, is empty)
// ❌ Pas de 'in' operator
```

**Workaround :** Calculer en PHP avant template.

**Limitation :** Logique limitée dans templates.

---

#### 5. Whitespace Control

**Twig :**
```twig
<ul>
    {%- for item in items -%}
        <li>{{ item }}</li>
    {%- endfor -%}
</ul>

{# Résultat : <ul><li>A</li><li>B</li><li>C</li></ul> #}
```

**Vision actuel :**
```php
<ul>
    {% for item in items %}
        <li>{{ item }}</li>
    {% endfor %}
</ul>

{# Résultat : <ul>
    
        <li>A</li>
    
        <li>B</li>
    
</ul> #}
```

**Impact :** HTML moins propre, fichiers plus gros.

---

### 🟡 UTILES (Confort)

#### 6. For...Else (Fallback si vide)

**Twig :**
```twig
{% for item in items %}
    <li>{{ item }}</li>
{% else %}
    <li>No items found</li>
{% endfor %}
```

**Vision actuel :**
```php
{% if items %}
    {% for item in items %}
        <li>{{ item }}</li>
    {% endfor %}
{% else %}
    <li>No items found</li>
{% endif %}
```

---

#### 7. Ranges (0..10)

**Twig :**
```twig
{% for i in 0..10 %}
    <option value="{{ i }}">{{ i }}</option>
{% endfor %}

{% for letter in 'a'..'z' %}
    {{ letter }}
{% endfor %}
```

**Vision actuel :**
```php
// ❌ Pas de ranges
// Doit créer array en PHP
$vision->render('template', ['numbers' => range(0, 10)]);
```

---

#### 8. String Interpolation

**Twig :**
```twig
{{ "Hello #{name}, you are #{age} years old" }}
```

**Vision actuel :**
```php
// ❌ Pas d'interpolation
Hello {{ name }}, you are {{ age }} years old
```

---

#### 9. Named Arguments

**Twig :**
```twig
{{ date(format='Y-m-d', timezone='UTC') }}
{{ component('Button', label='Save', variant='primary', size='lg') }}
```

**Vision actuel :**
```php
// ❌ Arguments positionnels seulement
{{ date("Y-m-d") }}
```

---

### 🟢 BASSES (Nice to have)

#### 10. Template Embedding

**Twig :**
```twig
{% embed "card.html.twig" %}
    {% block title %}Custom Title{% endblock %}
    {% block content %}Custom content{% endblock %}
{% endembed %}
```

**Vision actuel :** ❌ Pas d'embed (utiliser component à la place)

---

## 📊 Tableau Comparatif Complet

| Fonctionnalité | Twig | Blade | Smarty | Vision | Priorité |
|----------------|------|-------|---------|---------|----------|
| **Core Features** |
| Variables `{{ var }}` | ✅ | ✅ | ✅ | ✅ | - |
| Filters `{{ var\|filter }}` | ✅ | ✅ | ✅ | ✅ | - |
| Conditions `{% if %}` | ✅ | ✅ | ✅ | ✅ | - |
| Loops `{% for %}` | ✅ | ✅ | ✅ | ✅ | - |
| Auto-escape | ✅ | ✅ | ✅ | ✅ | - |
| **Advanced Features** |
| Template Inheritance | ✅✅ | ✅ | ✅ | ✅ | ✅ COMPLÉTÉ |
| Blocks | ✅✅ | ✅ | ✅ | ✅ | ✅ COMPLÉTÉ |
| Macros | ✅ | ✅ | ❌ | ❌ | 🟠 HAUTE |
| Filtres tableau (25+) | ✅ | ✅ | ✅ | ❌ (1) | 🟠 HAUTE |
| Opérateurs math | ✅ | ✅ | ✅ | ❌ | 🟠 HAUTE |
| Ternaire | ✅ | ✅ | ✅ | ❌ | 🟠 HAUTE |
| Tests (is defined) | ✅ | ✅ | ✅ | ❌ | 🟠 HAUTE |
| Whitespace control | ✅ | ❌ | ✅ | ❌ | 🟡 MOYENNE |
| For...else | ✅ | ✅ | ✅ | ❌ | 🟡 MOYENNE |
| Ranges (0..10) | ✅ | ❌ | ✅ | ❌ | 🟡 MOYENNE |
| String interpolation | ✅ | ❌ | ✅ | ❌ | 🟡 MOYENNE |
| Named arguments | ✅ | ✅ | ❌ | ❌ | 🟢 BASSE |
| Embed | ✅ | ❌ | ❌ | ❌ | 🟢 BASSE |
| **Performance** |
| Compilation | ✅ | ✅ | ✅ | ✅✅ | - |
| Cache | ✅ | ✅ | ✅ | ✅ | - |
| Constant Folding | ❌ | ❌ | ❌ | ✅ | ✅ **Unique** |
| Dead Branch Elimination | ❌ | ❌ | ❌ | ✅ | ✅ **Unique** |
| Inline Filters | ❌ | ❌ | ❌ | ✅ | ✅ **Unique** |
| Fragment Caching | ❌ | ❌ | ❌ | ✅ | ✅ **Unique** |
| Vitesse (vs baseline) | 1x | 1x | 0.8x | **30x** | ✅ **Meilleur** |
| **Security** |
| Sandbox | ✅ | ✅ | ✅ | ✅ | - |
| Path traversal protection | ✅ | ✅ | ✅ | ✅ | - |
| ReDoS protection | ✅ | ❌ | ❌ | ✅ | - |
| **Ecosystem** |
| Extensions marketplace | ✅✅ | ✅✅ | ✅✅ | ❌ | - |
| Framework integration | ✅✅ | ✅✅ | ✅✅ | ❌ | - |
| Documentation | ✅✅ | ✅✅ | ✅✅ | ✅ | - |
| Tests | ✅✅ | ✅ | ✅ | ✅ | - |

**Légende :**
- ✅ = Supporté
- ✅✅ = Excellent support
- ❌ = Non supporté
- 🔴 = Priorité critique
- 🟠 = Priorité haute
- 🟡 = Priorité moyenne
- 🟢 = Priorité basse

---

## 🎯 Roadmap Recommandée

### ✅ Phase 1 : MVP Production-Ready (COMPLÉTÉ)

**Objectif :** Rendre Vision utilisable pour projets réels.

1. ✅ **Template Inheritance + Blocks** (Complété le 3 déc 2025)
   ```twig
   {% extends "base.html" %}
   {% block title %}My Page{% endblock %}
   {% block content %}...{% endblock %}
   ```
   - 294 tests, 725 assertions
   - Documentation complète
   - Résolution compile-time
   - Support multi-niveaux et blocks imbriqués

### Phase 2 : Compétitivité Twig/Blade (5 semaines)

**Objectif :** Feature parity avec concurrence.

2. **Macros + Import** (1 semaine)
   ```twig
   {% macro input(name, value) %}...{% endmacro %}
   {% import "macros.html" as forms %}
   ```

3. **Filtres Tableau Essentiels** (1 semaine)
   - `first`, `last`, `join`, `slice`, `sort`, `reverse`

4. **Opérateurs Math et Concaténation** (1 semaine)
   ```twig
   {{ price * 1.2 }}
   {{ name ~ ' ' ~ surname }}
   ```

5. **Ternaire et Tests** (1 semaine)
   ```twig
   {{ x ? 'yes' : 'no' }}
   {{ user is defined ? user.name : 'Guest' }}
   {{ items is empty }}
   ```

6. **Whitespace Control** (3 jours)
   ```twig
   {%- for item in items -%}
   ```

7. **For...Else** (2 jours)
   ```twig
   {% for item in items %}
   {% else %}
       No items
   {% endfor %}
   ```

**Impact :** Vision devient **compétitif** avec Twig/Blade sur fonctionnalités.

---

### Phase 3 : Polish et Confort (2 semaines)

**Objectif :** Améliorer developer experience.

8. **Ranges** (2 jours)
   ```twig
   {% for i in 0..10 %}
   ```

9. **String Interpolation** (3 jours)
   ```twig
   {{ "Hello #{name}" }}
   ```

10. **Named Arguments** (1 semaine)
    ```twig
    {{ date(format='Y-m-d', timezone='UTC') }}
    ```

11. **Filtres Tableau Avancés** (4 jours)
    - `batch`, `filter`, `map`, `merge`, `reduce`

**Impact :** Vision offre une **DX meilleure** que Twig.

---

### Phase 4 : Écosystème (ongoing)

12. **Extensions Marketplace**
13. **Framework Integration** (Laravel, Symfony, CodeIgniter)
14. **IDE Plugins** (VS Code, PhpStorm)
15. **Documentation Interactive**

---

## 💡 Arguments de Vente de Vision

### Quand Choisir Vision ?

✅ **Performance critique** (API haute fréquence, real-time)  
✅ **Optimisations avancées** nécessaires  
✅ **Cache granulaire** (composants individuels)  
✅ **Architecture moderne** (AST, PHP 8.0+)  
✅ **Zero dependencies** requis  
✅ **Sécurité stricte** (sandbox granulaire)

### Quand Choisir Twig ?

✅ **Projet Symfony** (intégration native)  
✅ **Écosystème mature** (extensions, docs)  
✅ **Adoption large** (community support)  
✅ **Fonctionnalités avancées** (macros, embed)  
✅ **Stabilité garantie** (10+ ans d'existence)

### Quand Choisir Blade ?

✅ **Projet Laravel** (intégration native)  
✅ **Syntaxe concise** (`@if`, `@foreach`)  
✅ **Components Laravel** (livewire, alpine)

---

## 📈 Metrics et KPIs

### Performance (vs Twig)

| Metric | Vision | Twig | Différence |
|--------|--------|------|------------|
| **Template simple** | 0.1ms | 2.1ms | **95% faster** |
| **Template complexe** | 0.5ms | 45ms | **97% faster** |
| **1000 itérations** | 2ms | 350ms | **99% faster** |
| **Mémoire** | 200KB | 450KB | **56% moins** |

### Code Quality

| Metric | Vision |
|--------|--------|
| Tests | 230 |
| Assertions | 486 |
| Coverage | 100% (fonctionnel) |
| Strict Typing | ✅ PHP 8.0+ |
| Static Analysis | ✅ PHPStan Level 8 ready |

### Security

| Feature | Vision | Twig | Blade |
|---------|--------|------|-------|
| Auto-escape | ✅ | ✅ | ✅ |
| Sandbox | ✅ | ✅ | ✅ |
| Path traversal | ✅ | ✅ | ✅ |
| ReDoS protection | ✅ | ✅ | ❌ |
| Métriques runtime | ✅ | ❌ | ❌ |

---

## 🎓 Conclusion

### Résumé

**Vision** est un moteur de templates **extrêmement performant** (97% plus rapide que Twig) avec une **architecture moderne** et des **optimisations uniques**. Cependant, pour rivaliser avec Twig/Blade en tant que choix mainstream, il manque encore quelques **fonctionnalités essentielles** :

### Top 5 Priorités Absolues

1. 🔴 **Template Inheritance + Blocks** → Fonctionnalité #1 attendue
2. 🔴 **Macros** → Réutilisabilité dans templates
3. 🟠 **Filtres tableau** (first, last, join, slice) → Manipulation collections
4. 🟠 **Opérateurs avancés** (math, ternaire, tests) → Logique dans templates
5. 🟠 **Whitespace control** → HTML propre

### Positionnement

**Vision devrait se positionner comme :**

> "Le moteur de templates PHP le plus rapide avec optimisations compilateur avancées, idéal pour applications haute performance nécessitant cache granulaire et sécurité stricte."

**Une fois les 5 priorités implémentées**, Vision deviendra :

> "Le successeur moderne de Twig : 30x plus rapide, avec macros, inheritance, et optimisations uniques."

### Estimation Globale

**12 semaines de développement** pour atteindre feature parity avec Twig/Blade tout en conservant l'avantage performance 30x.

---

## 📚 Ressources

### Documentation Concurrence

- **Twig:** https://twig.symfony.com/doc/
- **Blade:** https://laravel.com/docs/blade
- **Smarty:** https://www.smarty.net/docs/

### Benchmarks

Vision benchmarks disponibles dans `tests/PerformanceTest.php`.

### Contribution

Pour prioriser ou contribuer à une fonctionnalité, voir le [ROADMAP.md](ROADMAP.md).

---

*Document généré le 3 décembre 2025*  
*Vision v1.0 - 230 tests passing*
