<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\FieldTree;

use CloakWP\ACF\{Block, BlockFieldIndex, BlockRegistry};
use CloakWP\ACF\Fields\InnerBlocks;
use Extended\ACF\Fields\{FlexibleContent, Group, Layout, PostObject, Relationship, Repeater};
use Extended\ACF\Key;
use PHPUnit\Framework\TestCase;

final class BlockFieldIndexTest extends TestCase
{
  private function block(string $name, array $fields): Block
  {
    return new class($name, $fields) extends Block {
      public function __construct(private string $name, private array $testFields) {}
      public function getFieldGroupSettings(): array
      {
        return ['name' => 'acf/' . $this->name, 'key' => 'block_' . $this->name,
          'title' => $this->name, 'fields' => $this->testFields];
      }
    };
  }

  public function testNestedKeysMatchExportsWithoutExportingDuringLookup(): void
  {
    $parent = 'block_index_nested';
    $selector = PostObject::make('Target', 'target');
    $fields = [Group::make('Content', 'content')->fields([
      Repeater::make('Rows', 'rows')->fields([$selector]),
    ])];
    $block = $this->block('index_nested', $fields);
    $rawKey = $parent . '_content_rows_target';
    $key = 'field_' . Key::hash($rawKey);
    $this->assertFalse(Key::has($rawKey));
    $match = (new BlockFieldIndex([$block]))->find($key, 'post_object');
    $this->assertSame(['block' => $block, 'layouts' => []], $match);
    $this->assertFalse(Key::has($rawKey));
    $export = $fields[0]->toArray($parent);
    $this->assertSame($key, $export['sub_fields'][0]['sub_fields'][0]['key']);
  }

  public function testExplicitKeysAndOrdinaryFlexibleLayouts(): void
  {
    $explicit = Relationship::make('Selected', 'selected')->key('field_index_explicit');
    $fields = [FlexibleContent::make('Sections', 'sections')->layouts([
      Layout::make('Text', 'text')->fields([$explicit]),
    ])];
    $block = $this->block('index_flexible', $fields);
    $index = new BlockFieldIndex([$block]);
    $this->assertSame(['block' => $block, 'layouts' => []], $index->find('field_index_explicit', 'relationship'));
    $this->assertNull($index->find('field_index_explicit', 'taxonomy'));
    $this->assertNull($index->find('field_missing', 'post_object'));
  }

  public function testAnInnerLayoutSelectorResolvesItsOwnerAndLazyAncestor(): void
  {
    $registry = BlockRegistry::getInstance();
    $child = $this->block('index_child', [PostObject::make('Target', 'target')]);
    $registry->addBlock($child);
    $inner = InnerBlocks::make('Children', 'children')->include(['acf/index_child']);
    $owner = $this->block('index_owner', [$inner]);
    $registry->addBlock($owner);
    $rawParent = 'block_index_owner';
    $key = 'field_' . Key::hash($rawParent . '_children_' . Key::sanitize('acf/index_child') . '_target');
    $match = (new BlockFieldIndex([$child, $owner]))->find($key, 'post_object');
    $this->assertSame(['block' => $owner,
      'layouts' => ['field_' . Key::hash($rawParent . '_children')]], $match);
    $export = $inner->toArray($rawParent);
    $this->assertSame($key, $export['layouts'][0]['sub_fields'][0]['key']);
  }

  public function testAmbiguousOwnersRetainFullRegistration(): void
  {
    // Raw settings avoid the builder's early uniqueness check so the index can
    // handle third-party builders supplying the same explicit key.
    $field = PostObject::make('Target');
    $reflection = new \ReflectionProperty($field, 'settings');
    $reflection->setValue($field, $field->settings + ['key' => 'field_shared_index']);
    $first = $this->block('index_first', [$field]);
    $second = $this->block('index_second', [clone $field]);
    $this->assertNull((new BlockFieldIndex([$first, $second]))->find('field_shared_index', 'post_object'));
  }
}
