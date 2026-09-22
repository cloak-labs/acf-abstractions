<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\Query;

use CloakWP\ACF\Fields\Query;
use CloakWP\ACF\Query\QueryArgsBuilder;
use CloakWP\ACF\Query\QueryRequest;

final class QueryRequestAndArgsTest extends QueryTestCase
{
  public function testMissingValueFallsBackToConfiguredDefaults(): void
  {
    $definition = Query::make()
      ->postType('team')
      ->unlimited()
      ->withLimit(max: 100, unlimited: true)
      ->orderBy(['menu_order' => 'ASC', 'title' => 'ASC'])
      ->toDefinition();

    $request = QueryRequest::from($definition, null);
    $args = (new QueryArgsBuilder())->compile($request);

    $this->assertSame('all', $request->selection);
    $this->assertSame(['team'], $request->postTypes);
    $this->assertSame(-1, $request->postsPerPage);
    $this->assertSame(['menu_order' => 'ASC', 'title' => 'ASC'], $request->orderby);
    $this->assertSame(['team'], $args['post_type']);
    $this->assertSame(-1, $args['posts_per_page']);
    $this->assertFalse($args['suppress_filters']);
    $this->assertTrue($args['no_found_rows']);
    $this->assertTrue($args['ignore_sticky_posts']);
    $this->assertSame(['publish'], $args['post_status']);
  }

  public function testClampsLimitAndSanitizesOrderby(): void
  {
    $definition = Query::make()
      ->postType('post')
      ->withLimit(max: 10)
      ->withOrdering(choices: ['date', 'title'], default: ['date' => 'DESC'])
      ->toDefinition();

    $request = QueryRequest::from($definition, [
      'selection' => 'all',
      'limit' => 99,
      'orderby' => 'title',
      'order' => 'ASC',
    ]);
    $args = (new QueryArgsBuilder())->compile($request);

    $this->assertSame(10, $request->postsPerPage);
    $this->assertSame(['title' => 'ASC', 'date' => 'DESC'], $args['orderby']);
  }

  public function testEmptyLimitUsesMaxWhenUnlimitedIsDisabled(): void
  {
    $definition = Query::make()->postType('post')->withLimit(max: 8)->toDefinition();
    $request = QueryRequest::from($definition, ['selection' => 'all']);

    $this->assertSame(8, $request->postsPerPage);
  }

  public function testManualSelectionUsesPostInOrderAndSkipsEmptyIds(): void
  {
    $definition = Query::make()->postType('team')->toDefinition();
    $builder = new QueryArgsBuilder();

    $empty = QueryRequest::from($definition, [
      'selection' => 'some',
      'posts' => [],
    ]);
    $this->assertNull($builder->compile($empty));

    $request = QueryRequest::from($definition, [
      'selection' => 'some',
      'posts' => ['12', 0, 8, 'nope'],
      'orderby' => 'date',
    ]);
    $args = $builder->compile($request);

    $this->assertSame([12, 8], $args['post__in']);
    $this->assertSame('post__in', $args['orderby']);
    $this->assertSame(2, $args['posts_per_page']);
  }

  public function testAllowlistFallsBackToAllAllowedTypes(): void
  {
    $definition = Query::make()->postTypes(['post', 'portfolio'])->toDefinition();
    $request = QueryRequest::from($definition, ['selection' => 'all']);

    $this->assertSame(['post', 'portfolio'], $request->postTypes);
  }

  public function testAllowlistIntersectsEditorSelectionAndDropsUnknownTypes(): void
  {
    $definition = Query::make()->postTypes(['post', 'portfolio'])->toDefinition();
    $request = QueryRequest::from($definition, [
      'selection' => 'all',
      'post_type' => ['portfolio', 'secret', 'post'],
    ]);

    $this->assertSame(['portfolio', 'post'], $request->postTypes);
  }

  public function testAnyPostTypeWithoutSelectionDoesNotQuery(): void
  {
    $definition = Query::make()->anyPostType()->toDefinition();
    $request = QueryRequest::from($definition, ['selection' => 'all']);

    $this->assertSame([], $request->postTypes);
    $this->assertNull((new QueryArgsBuilder())->compile($request));
  }

  public function testTaxonomyFiltersKeepOnlyConfiguredTermIds(): void
  {
    $definition = Query::make()
      ->postType('post')
      ->withTaxonomyFilters(['category', 'post_tag'])
      ->toDefinition();

    $request = QueryRequest::from($definition, [
      'selection' => 'all',
      'taxonomies' => [
        'category' => ['4', 0, 9],
        'post_tag' => [3],
        'ignored' => [1],
      ],
    ]);
    $args = (new QueryArgsBuilder())->compile($request);

    $this->assertSame('AND', $args['tax_query']['relation']);
    $this->assertSame([4, 9], $args['tax_query'][0]['terms']);
    $this->assertSame('category', $args['tax_query'][0]['taxonomy']);
    $this->assertSame([3], $args['tax_query'][1]['terms']);
  }

  public function testRandomOrderingIsOptInViaChoices(): void
  {
    $definition = Query::make()
      ->postType('post')
      ->withOrdering(choices: ['date', 'rand'], default: ['date' => 'DESC'])
      ->toDefinition();

    $request = QueryRequest::from($definition, [
      'selection' => 'all',
      'orderby' => 'rand',
    ]);
    $args = (new QueryArgsBuilder())->compile($request);

    $this->assertSame('rand', $args['orderby']);
  }

  public function testUnknownOrderbyFallsBackToDefault(): void
  {
    $definition = Query::make()
      ->postType('post')
      ->withOrdering(choices: ['date'], default: ['date' => 'DESC'])
      ->toDefinition();

    $request = QueryRequest::from($definition, [
      'selection' => 'all',
      'orderby' => 'comment_count',
    ]);

    $this->assertSame(['date' => 'DESC'], $request->orderby);
  }

  public function testAutomaticQueriesDefaultToMenuOrderThenDate(): void
  {
    $definition = Query::make()->postType('team')->toDefinition();
    $request = QueryRequest::from($definition, null);
    $args = (new QueryArgsBuilder())->compile($request);

    $this->assertSame(['menu_order' => 'ASC', 'date' => 'DESC'], $request->orderby);
    $this->assertSame(['menu_order' => 'ASC', 'date' => 'DESC'], $args['orderby']);
  }

  public function testWithOrderingWithoutDefaultKeepsMenuOrderThenDate(): void
  {
    $definition = Query::make()
      ->postType('team')
      ->withOrdering(choices: ['menu_order', 'title', 'date'])
      ->toDefinition();

    $this->assertSame(['menu_order' => 'ASC', 'date' => 'DESC'], $definition->defaultOrderby);
  }

  public function testWithOrderingWithoutDefaultAddsDateTiebreakerToFirstChoice(): void
  {
    $definition = Query::make()
      ->postType('post')
      ->withOrdering(choices: ['title', 'date'])
      ->toDefinition();

    $this->assertSame(['title' => 'DESC', 'date' => 'DESC'], $definition->defaultOrderby);
  }

  public function testExplicitOrderByOverridesThePackageDefault(): void
  {
    $definition = Query::make()
      ->postType('post')
      ->orderBy(['date' => 'ASC'])
      ->toDefinition();
    $args = (new QueryArgsBuilder())->compile(QueryRequest::from($definition, null));

    $this->assertSame(['date' => 'ASC'], $args['orderby']);
  }

  public function testEditorMenuOrderKeepsADateTiebreaker(): void
  {
    $definition = Query::make()
      ->postType('team')
      ->withOrdering(choices: ['menu_order', 'title', 'date'])
      ->toDefinition();
    $args = (new QueryArgsBuilder())->compile(QueryRequest::from($definition, [
      'selection' => 'all',
      'orderby' => 'menu_order',
      'order' => 'ASC',
    ]));

    $this->assertSame(['menu_order' => 'ASC', 'date' => 'DESC'], $args['orderby']);
  }

  public function testEditorDateOrderingStaysDateOnly(): void
  {
    $definition = Query::make()
      ->postType('post')
      ->withOrdering(choices: ['menu_order', 'date'], default: ['date' => 'DESC'])
      ->toDefinition();
    $args = (new QueryArgsBuilder())->compile(QueryRequest::from($definition, [
      'selection' => 'all',
      'orderby' => 'date',
      'order' => 'ASC',
    ]));

    $this->assertSame(['date' => 'ASC'], $args['orderby']);
  }
}
