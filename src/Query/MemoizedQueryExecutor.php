<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class MemoizedQueryExecutor implements QueryExecutor
{
  /** @var array<string, list<object>> */
  private array $cache = [];

  public function __construct(private QueryExecutor $inner)
  {
  }

  public function getPosts(array $args): array
  {
    $key = md5(serialize($this->normalize($args)));

    return $this->cache[$key] ??= $this->inner->getPosts($args);
  }

  public function reset(): void
  {
    $this->cache = [];
  }

  /**
   * @param array<string, mixed> $args
   * @return array<string, mixed>
   */
  private function normalize(array $args): array
  {
    ksort($args);

    if (isset($args['post_type']) && is_array($args['post_type'])) {
      $types = $args['post_type'];
      sort($types);
      $args['post_type'] = array_values($types);
    }

    return $args;
  }
}
