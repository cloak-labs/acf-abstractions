<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\Query;

use CloakWP\ACF\Fields\Query;
use CloakWP\ACF\Query\DefinitionRegistry;

final class FieldGenerationTest extends QueryTestCase
{
  public function testDefaultsToQueryLabelAndName(): void
  {
    $array = Query::make()->postType('team')->toArray('group_test');

    $this->assertSame('Query', $array['label']);
    $this->assertSame('query', $array['name']);
    $this->assertSame('group', $array['type']);
    $this->assertArrayHasKey('cloakwp_query_id', $array);
    $this->assertNotNull(DefinitionRegistry::get($array['cloakwp_query_id']));
  }

  public function testUniqueInstanceNames(): void
  {
    $related = Query::make('Related Content', 'related_query')
      ->postTypes(['post', 'portfolio'])
      ->toArray('group_test');

    $this->assertSame('related_query', $related['name']);
    $this->assertSame('Related Content', $related['label']);
  }

  public function testLockedPostTypeOmitsPostTypeFieldAndRestrictsRelationship(): void
  {
    $array = Query::make()->postType('team')->toArray('group_locked');
    $names = $this->fieldNames($array['sub_fields']);

    $this->assertSame(['selection', 'posts'], $names);
    $this->assertSame(['team'], $this->findField($array['sub_fields'], 'posts')['post_type']);
    $this->assertSame('id', $this->findField($array['sub_fields'], 'posts')['return_format']);
  }

  public function testAllowlistAndAnyPostTypeIncludePostTypeSelect(): void
  {
    $allowlist = Query::make()->postTypes(['post', 'portfolio'])->toArray('group_allow');
    $any = Query::make('Open', 'open_query')->anyPostType()->toArray('group_any');

    $this->assertContains('post_type', $this->fieldNames($allowlist['sub_fields']));
    $this->assertContains('post_type', $this->fieldNames($any['sub_fields']));
    $this->assertSame(['post', 'portfolio'], $this->findField($allowlist['sub_fields'], 'posts')['post_type']);
    $this->assertArrayNotHasKey('post_type', $this->findField($any['sub_fields'], 'posts'));
  }

  public function testOptionalLimitOrderingAndTaxonomyFields(): void
  {
    $array = Query::make()
      ->postType('post')
      ->withLimit(max: 100, unlimited: true)
      ->withOrdering(
        choices: ['menu_order', 'title', 'date', 'rand'],
        default: ['menu_order' => 'ASC', 'title' => 'ASC'],
      )
      ->withTaxonomyFilters(['category' => 'Categories', 'post_tag'])
      ->toArray('group_full');

    $names = $this->fieldNames($array['sub_fields']);
    $this->assertSame(
      ['selection', 'limit', 'orderby', 'order', 'taxonomies', 'posts'],
      $names,
    );

    $limit = $this->findField($array['sub_fields'], 'limit');
    $this->assertSame(1.0, $limit['min']);
    $this->assertSame(100.0, $limit['max']);

    $orderby = $this->findField($array['sub_fields'], 'orderby');
    $this->assertArrayHasKey('menu_order', $orderby['choices']);
    $this->assertArrayHasKey('rand', $orderby['choices']);

    $taxonomies = $this->findField($array['sub_fields'], 'taxonomies');
    $this->assertSame(['category', 'post_tag'], $this->fieldNames($taxonomies['sub_fields']));
    $this->assertSame('Categories', $this->findField($taxonomies['sub_fields'], 'category')['label']);
  }

  public function testToArrayIsIdempotentForTheSameInstance(): void
  {
    $query = Query::make()->postType('team');
    $first = $query->toArray('group_once');
    $second = $query->toArray('group_once');

    $this->assertSame($first['cloakwp_query_id'], $second['cloakwp_query_id']);
    $this->assertSame($first['key'], $second['key']);
  }
}
