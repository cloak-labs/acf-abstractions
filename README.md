# CloakWP - ACF Abstractions

A set of OOP abstractions around ACF to improve developer experience.

This package is meant to be used alongside the wonderful [`vinkla/extended-acf`](https://github.com/vinkla/extended-acf/) package, which provides OOP abstractions for registering fields. This package simply extends that package with some useful extras:

- Register ACF Groups with the `FieldGroup` class (a wrapper around `vinkla/extended-acf`'s `register_extended_field_group` function)
- Register ACF Blocks with the `Block` class
- Register ACF Options Pages with the `OptionsPage` class
- Some useful, special fields that extend ACF's built-in field types:
  - `InnerBlocks` - An auto-populated ACF `Flexible Content` field enabling you to select/arrange ACF blocks (assuming you register your ACF blocks via this package's `Block` class). Assigning `InnerBlocks` as a field of an ACF block enables nesting blocks within each other for powerful block composability. You can control which blocks are available for selection on a per-instance basis via the `includes` and `excludes` methods.
  - `Alignment` - An auto-populated ACF `Button Group` field with options for `left`, `center`, `right`, and `justify` (displayed as icons). You can control which of those 4 options are available on a per-instance basis.
  - `MenuSelect` - An auto-populated ACF `Select` field allowing you to select a registered WordPress menu.
  - `PostTypeSelect` - An auto-populated ACF `Select` field allowing you to select a registered WordPress post type.
  - `ThemeColorPicker` - An auto-populated ACF `Radio Button` field allowing you to select a color from the active theme's `theme.json` color palette, with special CSS styling to mimic the built-in Gutenberg color picker.

## Installation

```bash
composer require cloakwp/acf-abstractions
```

## Usage

Docs coming soon -- for now, see the source code of this package's `src` directory; it's mostly self-documenting.
