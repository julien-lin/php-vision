# Vision Macros

Les macros permettent de créer des fragments réutilisables dans vos templates, inspirés de Twig.

## Définir une macro

```
{% macro button(label, type="button") %}
<button type="{{ type }}">{{ label }}</button>
{% endmacro %}
```

- Paramètres: `label` requis, `type` avec valeur par défaut `"button"`.
- Les valeurs par défaut peuvent être des chaînes, nombres, booléens ou `null`.

## Appeler une macro

```
{{ button('Click me', 'submit') }}
```

- Arguments positionnels et nommés supportés: `{{ button(label='Click', type='submit') }}`
- Les arguments littéraux (chaînes, nombres, booléens, `null`) sont compilés en valeurs PHP.
- Les variables sont résolues via le contexte: `{{ greet(username) }}`.

## Importer des macros

```
{% import "macros.html" as ui %}

{{ ui.button('Save') }}
```

- `macros.html` peut contenir plusieurs macros. Elles sont accessibles via l'alias (`ui`).
- Si la macro importée n'existe pas, une erreur est levée.

## Filtres dans les macros

Les filtres Vision fonctionnent dans le corps des macros et sur les variables:

```
{% macro title(text) %}
<h1>{{ text | upper }}</h1>
{% endmacro %}

{{ title('hello world') }}
```

## Limitations actuelles

- Les appels de macros imbriqués dans le corps d'une autre macro ne sont pas pris en charge (test ignoré).
- Les macros sont inline-ées au moment de la compilation, il n'y a pas de fonctions PHP générées.

## Bonnes pratiques

- Préférez des paramètres nommés pour la clarté sur les macros à plusieurs paramètres.
- Évitez de multiplier les effets de bord: les macros doivent rester des rendus déterministes.

