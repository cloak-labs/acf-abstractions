<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class QueryRequest
{
  /**
   * @param list<string> $postTypes
   * @param list<int> $postIn
   * @param array<string, string> $orderby
   * @param list<array<string, mixed>> $taxQuery
   * @param list<string> $postStatus
   */
  public function __construct(
    public string $selection,
    public array $postTypes,
    public array $postIn,
    public int $postsPerPage,
    public string $orderbyMode,
    public array $orderby,
    public array $taxQuery,
    public array $postStatus,
  ) {
  }

  public static function from(Definition $definition, mixed $value): self
  {
    $value = is_array($value) ? $value : [];

    $selection = ($value['selection'] ?? 'all') === 'some' ? 'some' : 'all';
    $postTypes = self::resolvePostTypes($definition, $value['post_type'] ?? null);
    $postIn = self::positiveInts($value['posts'] ?? []);
    $postsPerPage = self::resolveLimit($definition, $value['limit'] ?? null, $selection);
    [$orderbyMode, $orderby] = self::resolveOrderby($definition, $value, $selection);
    $taxQuery = $selection === 'some' ? [] : self::resolveTaxQuery($definition, $value['taxonomies'] ?? []);

    return new self(
      selection: $selection,
      postTypes: $postTypes,
      postIn: $postIn,
      postsPerPage: $postsPerPage,
      orderbyMode: $orderbyMode,
      orderby: $orderby,
      taxQuery: $taxQuery,
      postStatus: $definition->postStatus,
    );
  }

  /**
   * @return list<string>
   */
  private static function resolvePostTypes(Definition $definition, mixed $selected): array
  {
    if ($definition->policy === PostTypePolicy::Locked) {
      return $definition->allowedPostTypes;
    }

    $selectedTypes = self::stringList($selected);
    $selectedTypes = array_values(array_filter(
      $selectedTypes,
      static fn(string $slug): bool => self::isPostTypeSlug($slug),
    ));

    if ($definition->policy === PostTypePolicy::Allowlist) {
      if ($selectedTypes === []) {
        return $definition->allowedPostTypes;
      }

      return array_values(array_intersect($selectedTypes, $definition->allowedPostTypes));
    }

    return $selectedTypes;
  }

  private static function resolveLimit(Definition $definition, mixed $raw, string $selection): int
  {
    if ($selection === 'some') {
      return -1;
    }

    if ($raw === null || $raw === '' || $raw === false) {
      if ($definition->unlimited) {
        return -1;
      }

      return $definition->defaultLimit ?? $definition->maxPosts;
    }

    $limit = (int) $raw;
    if ($limit < 1) {
      return $definition->unlimited ? -1 : ($definition->defaultLimit ?? $definition->maxPosts);
    }

    return min($limit, $definition->maxPosts);
  }

  /**
   * @param array<string, mixed> $value
   * @return array{0: string, 1: array<string, string>}
   */
  private static function resolveOrderby(Definition $definition, array $value, string $selection): array
  {
    if ($selection === 'some') {
      return ['post__in', []];
    }

    $rawOrderby = $value['orderby'] ?? null;
    $order = strtoupper((string) ($value['order'] ?? ''));
    if ($order !== 'ASC' && $order !== 'DESC') {
      $order = self::firstDirection($definition->defaultOrderby);
    }

    if (is_string($rawOrderby) && $rawOrderby !== '') {
      $key = AllowedOrderBy::normalizeKey($rawOrderby);
      $allowed = $key !== null && ($definition->orderbyChoices === [] || isset($definition->orderbyChoices[$key]));

      if ($key === 'rand' && $allowed) {
        return ['rand', []];
      }

      if ($key !== null && $allowed) {
        return ['query', DefaultPostOrder::withDateTiebreaker([$key => $order])];
      }
    }

    $default = $definition->defaultOrderby !== [] ? $definition->defaultOrderby : DefaultPostOrder::ORDERBY;
    if (isset($default['rand'])) {
      return ['rand', []];
    }

    return ['query', $default];
  }

  /**
   * @param array<string, string> $orderby
   */
  private static function firstDirection(array $orderby): string
  {
    $direction = strtoupper((string) (array_values($orderby)[0] ?? 'DESC'));

    return $direction === 'ASC' ? 'ASC' : 'DESC';
  }

  /**
   * @return list<array<string, mixed>>
   */
  private static function resolveTaxQuery(Definition $definition, mixed $raw): array
  {
    if ($definition->taxonomies === [] || !is_array($raw)) {
      return [];
    }

    $clauses = [];

    foreach ($definition->taxonomies as $taxonomy) {
      if (!self::isTaxonomySlug($taxonomy)) {
        continue;
      }

      $terms = self::positiveInts($raw[$taxonomy] ?? []);
      if ($terms === []) {
        continue;
      }

      $clauses[] = [
        'taxonomy' => $taxonomy,
        'field' => 'term_id',
        'terms' => $terms,
        'operator' => 'IN',
      ];
    }

    if (count($clauses) > 1) {
      return ['relation' => 'AND', ...$clauses];
    }

    return $clauses;
  }

  /**
   * @return list<int>
   */
  private static function positiveInts(mixed $value): array
  {
    if (!is_array($value)) {
      $value = $value === null || $value === '' || $value === false ? [] : [$value];
    }

    $ids = [];
    foreach ($value as $item) {
      if (is_numeric($item) && (int) $item > 0) {
        $ids[] = (int) $item;
      }
    }

    return array_values(array_unique($ids));
  }

  /**
   * @return list<string>
   */
  private static function stringList(mixed $value): array
  {
    if ($value === null || $value === '' || $value === false) {
      return [];
    }

    if (!is_array($value)) {
      return [(string) $value];
    }

    $items = [];
    foreach ($value as $item) {
      if (is_string($item) || is_int($item)) {
        $item = (string) $item;
        if ($item !== '') {
          $items[] = $item;
        }
      }
    }

    return array_values(array_unique($items));
  }

  public static function isPostTypeSlug(string $slug): bool
  {
    return (bool) preg_match('/^[a-z0-9_-]+$/', $slug);
  }

  public static function isTaxonomySlug(string $slug): bool
  {
    return self::isPostTypeSlug($slug);
  }
}
