<?php

declare(strict_types=1);

namespace CloakWP\ACF;

use CloakWP\ACF\Fields\InnerBlocks;
use Extended\ACF\Fields\Field;
use Extended\ACF\Fields\{PostObject, Relationship, Taxonomy, User};
use Extended\ACF\Key;

/** Find a selector's owning schema without exporting or importing field trees. */
final class BlockFieldIndex
{
  private const FIELD_CLASSES = [
    'post_object' => PostObject::class,
    'relationship' => Relationship::class,
    'taxonomy' => Taxonomy::class,
    'user' => User::class,
  ];
  /** @param list<Block> $blocks */
  public function __construct(private array $blocks) {}

  /** @return array{block: Block, layouts: list<string>}|null */
  public function find(string $key, string $type): ?array
  {
    if (!isset(self::FIELD_CLASSES[$type])) {
      return null;
    }
    $matches = [];
    $innerBlocks = [];
    foreach ($this->blocks as $block) {
      $settings = $block->getFieldGroupSettings();
      $this->walk($settings['fields'], Key::sanitize($settings['key']), $block,
        $key, $type, [], $matches, $innerBlocks);
    }
    if ($matches !== []) {
      return count($matches) === 1 ? $matches[0] : null;
    }
    foreach ($innerBlocks as [$field, $parent, $block, $ancestors]) {
      $rawKey = $this->rawKey($field, $parent);
      $ancestors[] = $field->settings['key'] ?? 'field_' . Key::hash($rawKey);
      $unused = [];
      $this->walk($field->getLayouts($parent), $rawKey, $block,
        $key, $type, $ancestors, $matches, $unused);
    }
    return count($matches) === 1 ? $matches[0] : null;
  }

  private function rawKey(Field $field, string $parent): string
  {
    // Extended ACF passes this unhashed path to child builders. Hashing the
    // parent first would produce different group/repeater/layout leaf keys.
    return $field->settings['key'] ?? $parent . '_' . Key::sanitize($field->settings['name']);
  }

  private function walk(array $fields, string $parent, Block $block, string $key,
    string $type, array $ancestors, array &$matches, array &$innerBlocks): void
  {
    foreach ($fields as $field) {
      if (!$field instanceof Field) {
        continue;
      }
      $rawKey = $this->rawKey($field, $parent);
      if ($field instanceof (self::FIELD_CLASSES[$type])
        && ($field->settings['key'] ?? 'field_' . Key::hash($rawKey)) === $key
      ) {
        $matches[] = ['block' => $block, 'layouts' => $ancestors];
      }
      if ($field instanceof InnerBlocks) {
        $innerBlocks[] = [$field, $parent, $block, $ancestors];
        continue;
      }
      foreach (['sub_fields', 'layouts'] as $children) {
        $this->walk($field->settings[$children] ?? [], $rawKey, $block,
          $key, $type, $ancestors, $matches, $innerBlocks);
      }
    }
  }
}
