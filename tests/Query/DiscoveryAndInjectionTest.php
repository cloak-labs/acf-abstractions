<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\Query;

use CloakWP\ACF\Fields\Query;
use CloakWP\ACF\Query\QueryDiscovery;
use CloakWP\ACF\Query\ResultInjector;

final class DiscoveryAndInjectionTest extends QueryTestCase
{
  public function testFindsMultipleAndNestedQueryFieldsIncludingMissingValues(): void
  {
    $query = Query::make()->postType('team')->into('members');
    $related = Query::make('Related', 'related_query')->postType('post');
    $queryArray = $query->toArray('group_disc');
    $relatedArray = $related->toArray('group_disc');

    $definitions = [
      $queryArray,
      [
        'name' => 'layout',
        'type' => 'group',
        'sub_fields' => [$relatedArray],
      ],
      [
        'name' => 'rows',
        'type' => 'repeater',
        'sub_fields' => [
          Query::make('Row Query', 'row_query')->postType('page')->toArray('group_row'),
        ],
      ],
    ];

    $found = QueryDiscovery::find(
      [
        'layout' => [
          'related_query' => ['selection' => 'some', 'posts' => [5]],
        ],
        'rows' => [
          ['row_query' => ['selection' => 'all']],
        ],
      ],
      $definitions,
    );

    $this->assertCount(3, $found);
    $this->assertSame(['query'], $found[0]['path']);
    $this->assertNull($found[0]['value']);
    $this->assertSame(['layout', 'related_query'], $found[1]['path']);
    $this->assertSame([5], $found[1]['value']['posts']);
    $this->assertSame(['rows', 0, 'row_query'], $found[2]['path']);
  }

  public function testLocalInjectionWritesResultsAtTheFieldPath(): void
  {
    $definition = Query::make()->postType('post')->toDefinition();
    $block = ResultInjector::inject(
      ['data' => ['query' => ['selection' => 'all']]],
      [['id' => 1]],
      ['query'],
      $definition,
      $this->context($definition, ['query']),
    );

    $this->assertSame('all', $block['data']['query']['selection']);
    $this->assertSame([['id' => 1]], $block['data']['query']['results']);
  }

  public function testIntoInjectsASiblingOnBlockData(): void
  {
    $definition = Query::make()->postType('team')->into('members')->toDefinition();
    $block = ResultInjector::inject(
      ['data' => ['card_style' => 'default']],
      [['id' => 3]],
      ['query'],
      $definition,
      $this->context($definition, ['query']),
    );

    $this->assertSame([['id' => 3]], $block['data']['members']);
    $this->assertSame('default', $block['data']['card_style']);
  }

  public function testInjectUsingReceivesTheCompleteBlock(): void
  {
    $definition = Query::make()
      ->postType('team')
      ->injectUsing(function (array $block, array $items) {
        $block['data']['custom'] = count($items);
        return $block;
      })
      ->toDefinition();

    $block = ResultInjector::inject(
      ['data' => []],
      [['id' => 1], ['id' => 2]],
      ['query'],
      $definition,
      $this->context($definition, ['query']),
    );

    $this->assertSame(2, $block['data']['custom']);
    $this->assertArrayNotHasKey('members', $block['data']);
  }

  /**
   * @param list<string|int> $path
   */
  private function context(\CloakWP\ACF\Query\Definition $definition, array $path): \CloakWP\ACF\Query\QueryContext
  {
    return new \CloakWP\ACF\Query\QueryContext(
      parsedBlock: ['data' => []],
      field: ['name' => 'query'],
      path: $path,
      rawValue: null,
      postId: 1,
      wpBlock: (object) ['name' => 'acf/test'],
      definition: $definition,
    );
  }
}
