# InnerBlocks layout loading

InnerBlocks builds its flexible-content layouts when ACF first loads the field,
rather than when the field group is registered. Requests that never load the
field avoid cloning, exporting, and registering every eligible block's children.
Once built, its layouts and subfields remain in ACF's local registry for the
request. Failed exports remain pending and retain partially exported builders
for retry. There is no persistent cache to invalidate.

The loader uses `acf/load_field/type=flexible_content` before ACF resolves the
layout children, and imports those children with `acf_add_local_field`. Generated
keys, layout order, include/exclude rules, value formatting, and editor choices
follow the existing layout builder. Deferred builders are scoped to the current
multisite blog and snapshot the InnerBlocks include/exclude configuration at
export time. Register the blocks before loading fields, as usual.

Use `acf_get_field($key)` when you need a complete definition. Registration arrays,
`InnerBlocks::toArray()`, and the raw `acf_get_local_field()` store initially have
empty layouts. Code that exports raw definitions without loading them first can
opt into eager generation:

```php
InnerBlocks::make('Blocks')
    ->include(['acf/heading', 'acf/paragraph'])
    ->lazyLayouts(false);
```

Without ACF's local-field API, exports are eager automatically. Lazy loading does
not remove any layout or disable any InnerBlocks field.

## Value-aware block parsing

With a compatible CloakWP BlockParser, lazy InnerBlocks registration also enables
value-aware loading. The parser inspects Gutenberg's saved field references before
loading a block's field group. An InnerBlocks field with no rows and no default
keeps empty layouts during that transformation, even inside a group or repeater.
Populated fields export only layouts named in saved rows. All occurrences of a
shared field key contribute their layout names, including mixed repeater rows.
Defaults and unknown value shapes keep the complete definition. Ordinary fields
and Query defaults are still discovered and formatted by ACF.

The parser uses a temporary copy of ACF's field cache for each transformation.
Partial definitions cannot affect later populated blocks, normal ACF reads, or
editor controls. Generated local definitions remain available for reuse within
the request. Custom field-cache implementations fall back to normal loading.

The `cloakwp/block/data` definition tree contains only the layouts used by that
block's InnerBlocks values. Integrations that inspect those layouts or supply rows
through runtime value filters can use `lazyLayouts(false)` on the affected field,
or disable this parser optimization globally:

```php
add_filter('cloakwp/block_parser/value_aware_fields', '__return_false');
```

## Verification

Run the usual PHPUnit suite with `composer test`. For actual ACF import, load,
format, and render checks, run the standalone integration check in a local
WordPress installation with ACF active:

```sh
docker exec pillar-wordpress wp --allow-root eval-file \
  /var/www/local-packages/acf-abstractions/tests/Integration/LazyInnerBlocksWordPress.php \
  --use-include --url=https://wp.localhost/hyland/
```

This registers a temporary in-memory block and field group for the request. It
does not save content or definitions to the database. The check covers deferred
registration, configuration snapshots, excluded children, distinct parent keys,
repeated loading, nested values, rendered controls, and eager exports.
The parser's `tests/Integration/ValueAwareFieldsWordPress.php` additionally checks
empty groups, mixed repeater rows, omitted Query defaults, cache isolation, and
ordinary ACF reads after parsing.

## Separate schema import from block registration

`Block::register()` registers WordPress block metadata, the site's catalog entry,
value callbacks. Its schema import can be skipped
through `cloakwp/acf/block/register_field_group` (`bool $register, Block $block`),
which defaults to true. Use `Block::registerFieldGroup()` to import a skipped
schema later; repeated calls on the same instance are ignored, as are UI-managed
fields. Register a complete eligible catalog before loading InnerBlocks.

`BlockFieldIndex` locates a native post-object, relationship, taxonomy, or user
selector among block builders without exporting them. `find($key, $type)` returns
its owning block and the lazy ancestor keys to load, or null for missing or
ambiguous matches. Custom key/export behavior requires validation against the
imported ACF field and a full-registration fallback. Catalog entries are keyed
by block name within each multisite blog.

`tests/Integration/TargetedBlockFieldsWordPress.php` verifies metadata and callback
retention, nested selector import, idempotent schema import, callback deduplication,
retry after a partial export, and multisite catalog isolation without saving posts.
