# Conditional Choices

Show or hide individual choices on ACF choice fields based on the live value of another field — without duplicating the field or changing its key/name.

This feature is an Extended ACF macro (`conditionalChoices()`) provided by `cloakwp/acf-abstractions`. It keeps one stable stored field and filters options in the editor via ACF’s JavaScript API, with matching server-side validation.

## Supported fields

- `Select` (including Select2 / stylized UI, single and multiple)
- `RadioButton`
- `Checkbox`
- `ButtonGroup`

Not supported in v1:

- AJAX / lazy-loaded Select choices
- User-created (“create”) choice values

## Basic usage

```php
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\RadioButton;

RadioButton::make('Height')
    ->choices([
        'auto' => 'Auto',
        'full' => 'Full',
        'stretch' => 'Stretch',
    ])
    ->default('auto')
    ->conditionalChoices([
        // Only show "stretch" when Layout is Background Image.
        'stretch' => [
            ConditionalLogic::where('hero_style', '==', 'bg_image'),
        ],
    ]);
```

## Hook timing

1. This plugin registers the `conditionalChoices()` macro at plugin load (not `acf/init`).
2. Field groups still register on `acf/include_fields` (`FieldGroup::register()`, `Block`, theme `registerBlocks()`).

Why not `acf/init` for macros? Current ACF fires `acf/include_fields` **before** `acf/init` inside `ACF::init()`. Extended ACF’s README still shows macros on `acf/init`, which is too late if you build fields during `include_fields`.

### Semantics

- Map keys are **choice values** (not labels).
- Each value is one or more `ConditionalLogic` groups.
- Multiple groups for one choice are **OR**’d.
- Rules inside one `ConditionalLogic` instance are **AND**’d (via `->and()`).
- Choices **not listed** in `conditionalChoices()` remain always available.
- When a selected value becomes unavailable, the editor falls back to the field’s default (or the first available choice). If that value becomes available again, the previous selection is restored.
- Field key, field name, REST shape, and stored meta key remain unchanged.

## Operators

Same operators as Extended ACF / ACF conditional logic:

- `==`, `!=`
- `>`, `<`
- `==contains`, `==pattern`
- `==empty`, `!=empty`

Falsy expected values (`0`, `'0'`, `false`) are preserved.

## Nesting / scope

Controllers are resolved in the target field’s nearest matching scope:

1. Same repeater row
2. Same flexible-content layout
3. Same Gutenberg ACF block instance
4. Same enclosing ACF form / field collection

Independent rows, layouts, and block instances do not affect each other.

## Extension filters

### PHP

- `cloakwp/conditional_choices/compiled` — customize the compiled payload after rule serialization.

### JavaScript (ACF filters / actions)

- Filter `cloakwp/conditional_choices/resolve_controller`
- Filter `cloakwp/conditional_choices/available_values`
- Action `cloakwp/conditional_choices/before_update`
- Action `cloakwp/conditional_choices/after_update`

## Server validation

`acf/validate_value` rejects submitted values that:

1. Are not in the field’s canonical `choices`, or
2. Are unavailable under the submitted controller state

Controllers are resolved from `$_POST` (classic forms) or ACF block local meta (`acf-{blockId}[…]` inputs in Gutenberg). Missing controllers fail closed **only when the submitted value has conditional rules** — always-available choices do not require controllers.

Trusted programmatic `update_field()` calls are outside interactive validation.
