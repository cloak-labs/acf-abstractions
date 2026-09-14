<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class QueryArgsBuilder
{
  /**
   * @return array<string, mixed>|null Null when the query should not run.
   */
  public function compile(QueryRequest $request): ?array
  {
    if ($request->postTypes === []) {
      return null;
    }

    if ($request->selection === 'some' && $request->postIn === []) {
      return null;
    }

    $args = [
      'post_type' => $request->postTypes,
      'post_status' => $request->postStatus,
      'posts_per_page' => $request->postsPerPage,
      'suppress_filters' => false,
      'no_found_rows' => true,
      'ignore_sticky_posts' => true,
    ];

    if ($request->selection === 'some') {
      $args['post__in'] = $request->postIn;
      $args['orderby'] = 'post__in';
      $args['posts_per_page'] = count($request->postIn);

      return $args;
    }

    if ($request->orderbyMode === 'rand') {
      $args['orderby'] = 'rand';
    } elseif ($request->orderby !== []) {
      $args['orderby'] = $request->orderby;
    }

    if ($request->taxQuery !== []) {
      $args['tax_query'] = $request->taxQuery;
    }

    return $args;
  }
}
