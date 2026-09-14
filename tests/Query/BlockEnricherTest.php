<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\Query;

use CloakWP\ACF\Fields\Query;
use CloakWP\ACF\Query\BlockEnricher;
use CloakWP\ACF\Query\MemoizedQueryExecutor;
use CloakWP\ACF\Query\QueryExecutor;

final class FakeQueryExecutor implements QueryExecutor
{
  public int $calls = 0;

  /** @var list<array<string, mixed>> */
  public array $received = [];

  /**
   * @param list<object> $posts
   */
  public function __construct(public array $posts = [])
  {
  }

  public function getPosts(array $args): array
  {
    $this->calls++;
    $this->received[] = $args;

    return $this->posts;
  }
}

final class BlockEnricherTest extends QueryTestCase
{
  public function testEnrichesUsingDefaultMapperAndLocalResults(): void
  {
    $query = Query::make()->postType('post');
    $array = $query->toArray('group_enrich');
    $post = (object) [
      'ID' => 11,
      'post_title' => 'Hello',
      'post_name' => 'hello',
      'post_excerpt' => 'Hi',
      'post_date' => '2026-01-01 00:00:00',
      'post_status' => 'publish',
    ];

    $executor = new FakeQueryExecutor([$post]);
    $enricher = new BlockEnricher($executor);

    $result = $enricher->enrich(
      ['name' => 'acf/posts', 'data' => []],
      [$array],
      (object) ['name' => 'acf/posts'],
      9,
    );

    $this->assertSame(1, $executor->calls);
    $this->assertSame(['post'], $executor->received[0]['post_type']);
    $this->assertSame(11, $result['data']['query']['results'][0]['id']);
    $this->assertSame('Hello', $result['data']['query']['results'][0]['title']);
  }

  public function testMapUsingAndIntoPreserveCustomShape(): void
  {
    $query = Query::make()
      ->postType('team')
      ->unlimited()
      ->into('members')
      ->mapUsing(fn(object $post) => (object) [
        'id' => $post->ID,
        'name' => $post->post_title,
      ]);
    $array = $query->toArray('group_team');

    $enricher = new BlockEnricher(new FakeQueryExecutor([
      (object) ['ID' => 4, 'post_title' => 'Ada'],
    ]));

    $result = $enricher->enrich(
      ['data' => ['card_style' => 'default']],
      [$array],
      (object) ['name' => 'acf/team'],
      1,
    );

    $this->assertSame(4, $result['data']['members'][0]->id);
    $this->assertSame('Ada', $result['data']['members'][0]->name);
    $this->assertSame('default', $result['data']['card_style']);
  }

  public function testResolveUsingBypassesTheExecutor(): void
  {
    $executor = new FakeQueryExecutor([(object) ['ID' => 1, 'post_title' => 'Nope']]);
    $query = Query::make()
      ->postType('post')
      ->resolveUsing(fn() => [(object) ['ID' => 22, 'post_title' => 'Custom', 'post_name' => 'c', 'post_excerpt' => '', 'post_date' => '', 'post_status' => 'publish']]);
    $array = $query->toArray('group_resolve');

    $result = (new BlockEnricher($executor))->enrich(
      ['data' => []],
      [$array],
      (object) ['name' => 'acf/x'],
      1,
    );

    $this->assertSame(0, $executor->calls);
    $this->assertSame(22, $result['data']['query']['results'][0]['id']);
  }

  public function testEnrichedDataIsAvailableToALaterBlockValueCallback(): void
  {
    $query = Query::make()->postType('team')->into('members');
    $array = $query->toArray('group_coexist');
    $enricher = new BlockEnricher(new FakeQueryExecutor([
      (object) ['ID' => 1, 'post_title' => 'Ada', 'post_name' => 'ada', 'post_excerpt' => '', 'post_date' => '', 'post_status' => 'publish'],
    ]));

    $parsed = $enricher->enrich(
      ['data' => ['card_style' => 'stills']],
      [$array],
      (object) ['name' => 'acf/team'],
      1,
    );

    $afterValue = (static function (array $block): array {
      if (($block['data']['card_style'] ?? null) === 'stills') {
        $block['data']['layout'] = 'grid';
      }
      return $block;
    })($parsed);

    $this->assertSame('grid', $afterValue['data']['layout']);
    $this->assertSame('Ada', $afterValue['data']['members'][0]['title']);
  }

  public function testMemoizedExecutorReusesIdenticalArgs(): void
  {
    $inner = new FakeQueryExecutor([(object) ['ID' => 1, 'post_title' => 'A', 'post_name' => '', 'post_excerpt' => '', 'post_date' => '', 'post_status' => 'publish']]);
    $memoized = new MemoizedQueryExecutor($inner);
    $args = ['post_type' => ['post'], 'posts_per_page' => 10];

    $first = $memoized->getPosts($args);
    $second = $memoized->getPosts(['posts_per_page' => 10, 'post_type' => ['post']]);

    $this->assertSame(1, $inner->calls);
    $this->assertSame($first, $second);
  }
}
