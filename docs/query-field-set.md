# Query field

A reusable ACF `Group` field that lets editors configure which posts populate a block, then injects the resolved posts into the block’s parsed/`blocks_data` payload.

`Query` is a normal field: put it anywhere `Block::fields()` accepts (including nested groups). Use unique names when a block has more than one instance.

Enrichment runs on `cloakwp/block/data` (after ACF fields are formatted, before `Block::value()` / `cloakwp/block`). `Block::value()` callbacks run after Query enrichment and may further modify the result.

## Basic usage

```php
use CloakWP\ACF\Block;
use CloakWP\ACF\Fields\Query;

Block::make(__DIR__ . '/block.json')
  ->fields([
    Query::make()
      ->postType('event')
      ->withLimit(max: 100, unlimited: true)
      ->withOrdering(
        choices: ['menu_order', 'title', 'date'],
        default: ['menu_order' => 'ASC', 'date' => 'DESC'],
      )
      ->into('events')
      ->mapUsing(fn ($event) => [
        'id' => $event->ID,
        'title' => $event->post_title,
      ]),
    // other block fields…
  ]);
```

`Query::make()` defaults to label/name `Query` / `query`. Additional instances need unique names:

```php
Query::make('Related Content', 'related_query')
  ->postTypes(['post', 'page'])
  ->into('related');
```

## Post-type policies

Call exactly one:

| Method | Editor | Automatic query |
| --- | --- | --- |
| `postType('event')` | Locked; no post-type field | Always that type |
| `postTypes(['post', 'page'])` | Optional multi-select of those types | Selected types, or all allowed if empty |
| `anyPostType()` | Optional post-type select | Only the types the editor picks (empty → no query) |

## Generated fields

Always wrapped in a group named `query` (or your custom name):

| Field | When |
| --- | --- |
| `selection` (`all` / `some`) | Always |
| `limit` | `withLimit()` |
| `post_type` | Allowlist / any policy |
| `orderby` / `order` | `withOrdering()` |
| `taxonomies.{slug}` | `withTaxonomyFilters()` |
| `posts` | Manual selection (`Relationship`, stored as IDs) |

When the group has no saved value, the query runs with its configured defaults (automatic selection).

Optional controls:

```php
Query::make()
  ->postType('post')
  ->unlimited()                 // posts_per_page = -1 when no limit is set
  ->withLimit(max: 24)          // editor field; empty uses max unless unlimited
  ->withTaxonomyFilters(['category', 'post_tag'])
  ->orderBy(['menu_order' => 'ASC', 'date' => 'DESC']) // default order without showing the UI
  ->postStatus(['publish']);
```

Without `withLimit()` / `unlimited()`, automatic queries are capped at **50** posts.

`rand` is available only when you include it in `withOrdering()` choices.

## Response injection

| API | Result |
| --- | --- |
| default | `data.{path}.results` (e.g. `data.query.results`) |
| `into('events')` | `data.events` |
| `mapUsing(fn (WP_Post $post) => …)` | Shape each post (default is a compact `{id,title,slug,excerpt,date,status}` object, no ACF) |
| `injectUsing(fn (array $block, array $items, QueryContext $context): array => …)` | Full control; cannot be combined with `into()` |
| `resolveUsing(fn (QueryRequest $request, QueryContext $context): array => …)` | Custom query execution; mapping and injection then apply |

## Query defaults

Compiled `WP_Query` args always include:

- `post_status` → `publish` (override with `postStatus()`)
- `suppress_filters` → `false`
- `no_found_rows` → `true`
- `ignore_sticky_posts` → `true`
- `orderby` → `menu_order ASC`, then `date DESC` (override with `orderBy()` / `withOrdering()`)
- editor-selected `orderby` values other than `date` / `rand` keep a `date DESC` tie-breaker; `date`, `rand`, and manual `post__in` stay as chosen
- editor limits clamped to `withLimit()` max
- manual mode: `post__in` + `orderby => post__in` (empty selection returns `[]` without querying)
- identical args reused for the rest of the request

## Hook

Requires `cloakwp/block-parser` with the `cloakwp/block/data` filter (passed the parsed block, ACF field-definition tree, `WP_Block`, and post ID). `acf-abstractions` listens to that hook; it does not add a Composer dependency on the parser.
