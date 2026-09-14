<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class Definition
{
  /**
   * @param list<string> $allowedPostTypes
   * @param array<string, string> $orderbyChoices
   * @param array<string, string> $defaultOrderby
   * @param list<string> $taxonomies
   * @param array<string, string> $taxonomyLabels
   * @param list<string> $postStatus
   * @param callable|null $mapper
   * @param callable|null $injector
   * @param callable|null $resolver
   */
  public function __construct(
    public PostTypePolicy $policy,
    public array $allowedPostTypes,
    public bool $unlimited,
    public int $maxPosts,
    public ?int $defaultLimit,
    public bool $hasLimitField,
    public bool $hasOrderingField,
    public array $orderbyChoices,
    public array $defaultOrderby,
    public array $taxonomies,
    public array $taxonomyLabels,
    public array $postStatus,
    public ?string $into,
    public mixed $mapper,
    public mixed $injector,
    public mixed $resolver,
  ) {
  }
}
