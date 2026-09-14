<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class QueryContext
{
  /**
   * @param array<string, mixed> $parsedBlock
   * @param array<string, mixed> $field
   * @param list<string|int> $path
   */
  public function __construct(
    public array $parsedBlock,
    public array $field,
    public array $path,
    public mixed $rawValue,
    public ?int $postId,
    public mixed $wpBlock,
    public Definition $definition,
  ) {
  }
}
