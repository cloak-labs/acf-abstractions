<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

interface QueryExecutor
{
  /**
   * @param array<string, mixed> $args
   * @return list<object>
   */
  public function getPosts(array $args): array;
}
