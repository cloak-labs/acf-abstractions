<?php

declare(strict_types=1);

namespace CloakWP\ACF\Fields;

use CloakWP\ACF\Query\AllowedOrderBy;
use CloakWP\ACF\Query\DefaultPostOrder;
use CloakWP\ACF\Query\Definition;
use CloakWP\ACF\Query\DefinitionRegistry;
use CloakWP\ACF\Query\FieldSchema;
use CloakWP\ACF\Query\PostTypePolicy;
use CloakWP\ACF\Query\QueryRequest;
use Extended\ACF\Fields\Group;
use InvalidArgumentException;

class Query extends Group
{
  private ?PostTypePolicy $policy = null;

  /** @var list<string> */
  private array $allowedPostTypes = [];

  private bool $unlimited = false;

  private int $maxPosts = 50;

  private ?int $defaultLimit = null;

  private bool $hasLimitField = false;

  private bool $hasOrderingField = false;

  /** @var array<string, string> */
  private array $orderbyChoices = [];

  /** @var array<string, string> */
  private array $defaultOrderby = DefaultPostOrder::ORDERBY;

  /** @var list<string> */
  private array $taxonomies = [];

  /** @var array<string, string> */
  private array $taxonomyLabels = [];

  /** @var list<string> */
  private array $postStatuses = ['publish'];

  private ?string $into = null;

  /** @var callable|null */
  private mixed $mapper = null;

  /** @var callable|null */
  private mixed $injector = null;

  /** @var callable|null */
  private mixed $resolver = null;

  private bool $compiled = false;

  public static function make(string $label = 'Query', string|null $name = null): static
  {
    $self = new static($label, $name);
    $self->layout('block');

    return $self;
  }

  public function postType(string $postType): static
  {
    $this->assertPolicyNotSet();
    $slug = $this->assertPostTypeSlug($postType);
    $this->policy = PostTypePolicy::Locked;
    $this->allowedPostTypes = [$slug];

    return $this;
  }

  /**
   * @param list<string> $postTypes
   */
  public function postTypes(array $postTypes): static
  {
    $this->assertPolicyNotSet();

    if ($postTypes === []) {
      throw new InvalidArgumentException('Query::postTypes() requires at least one post type.');
    }

    $slugs = [];
    foreach ($postTypes as $postType) {
      $slugs[] = $this->assertPostTypeSlug((string) $postType);
    }

    $this->policy = PostTypePolicy::Allowlist;
    $this->allowedPostTypes = array_values(array_unique($slugs));

    return $this;
  }

  public function anyPostType(): static
  {
    $this->assertPolicyNotSet();
    $this->policy = PostTypePolicy::Any;
    $this->allowedPostTypes = [];

    return $this;
  }

  public function unlimited(bool $unlimited = true): static
  {
    $this->unlimited = $unlimited;

    return $this;
  }

  public function withLimit(int $max, ?int $default = null, bool $unlimited = false): static
  {
    if ($max < 1) {
      throw new InvalidArgumentException('Query::withLimit() max must be at least 1.');
    }

    if ($default !== null && $default < 1) {
      throw new InvalidArgumentException('Query::withLimit() default must be at least 1.');
    }

    $this->hasLimitField = true;
    $this->maxPosts = $max;
    $this->defaultLimit = $default;
    if ($unlimited) {
      $this->unlimited = true;
    }

    return $this;
  }

  /**
   * @param list<string>|array<string, string> $choices
   * @param array<string, string> $default
   */
  public function withOrdering(array $choices, array $default = []): static
  {
    if ($choices === []) {
      throw new InvalidArgumentException('Query::withOrdering() requires at least one orderby choice.');
    }

    $this->hasOrderingField = true;
    $this->orderbyChoices = $this->normalizeOrderbyChoices($choices);

    if ($default !== []) {
      $this->defaultOrderby = $this->normalizeOrderby($default);
    } else {
      $primary = array_key_first($this->defaultOrderby);
      if ($primary === null || !isset($this->orderbyChoices[$primary])) {
        $first = array_key_first($this->orderbyChoices);
        if (is_string($first)) {
          $this->defaultOrderby = $this->normalizeOrderby(
            $first === 'rand'
              ? ['rand' => 'DESC']
              : [$first => $first === 'menu_order' ? 'ASC' : 'DESC', 'date' => 'DESC'],
          );
        }
      }
    }

    return $this;
  }

  /**
   * @param array<string, string> $orderby
   */
  public function orderBy(array $orderby): static
  {
    $this->defaultOrderby = $this->normalizeOrderby($orderby);

    return $this;
  }

  /**
   * @param list<string>|array<string, string> $taxonomies
   */
  public function withTaxonomyFilters(array $taxonomies): static
  {
    if ($taxonomies === []) {
      throw new InvalidArgumentException('Query::withTaxonomyFilters() requires at least one taxonomy.');
    }

    $slugs = [];
    $labels = [];

    foreach ($taxonomies as $key => $value) {
      if (is_int($key)) {
        $slug = $this->assertTaxonomySlug((string) $value);
        $slugs[] = $slug;
        continue;
      }

      $slug = $this->assertTaxonomySlug((string) $key);
      $slugs[] = $slug;
      $labels[$slug] = (string) $value;
    }

    $this->taxonomies = array_values(array_unique($slugs));
    $this->taxonomyLabels = $labels;

    return $this;
  }

  /**
   * @param list<string> $statuses
   */
  public function postStatus(array $statuses): static
  {
    if ($statuses === []) {
      throw new InvalidArgumentException('Query::postStatus() requires at least one status.');
    }

    $allowed = ['publish', 'draft', 'future', 'pending', 'private', 'inherit', 'any'];
    $normalized = [];

    foreach ($statuses as $status) {
      $status = (string) $status;
      if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException("Invalid post status [{$status}].");
      }
      $normalized[] = $status;
    }

    $this->postStatuses = array_values(array_unique($normalized));

    return $this;
  }

  public function into(string $key): static
  {
    if ($key === '') {
      throw new InvalidArgumentException('Query::into() requires a non-empty field name.');
    }

    if ($this->injector !== null) {
      throw new InvalidArgumentException('Query cannot use both into() and injectUsing(); choose one.');
    }

    $this->into = $key;

    return $this;
  }

  public function mapUsing(callable $mapper): static
  {
    $this->mapper = $mapper;

    return $this;
  }

  public function injectUsing(callable $injector): static
  {
    if ($this->into !== null) {
      throw new InvalidArgumentException('Query cannot use both into() and injectUsing(); choose one.');
    }

    $this->injector = $injector;

    return $this;
  }

  public function resolveUsing(callable $resolver): static
  {
    $this->resolver = $resolver;

    return $this;
  }

  /** @internal */
  public function toArray(?string $parentKey = null): array
  {
    $this->compile();

    return parent::toArray($parentKey);
  }

  public function compile(): static
  {
    if ($this->compiled) {
      return $this;
    }

    if ($this->policy === null) {
      throw new InvalidArgumentException(
        'Query requires a post-type policy. Call postType(), postTypes(), or anyPostType().',
      );
    }

    $definition = $this->toDefinition();
    $id = DefinitionRegistry::put($definition);

    $this->withSettings(['cloakwp_query_id' => $id]);
    $this->fields(FieldSchema::fields($definition));
    $this->compiled = true;

    return $this;
  }

  public function toDefinition(): Definition
  {
    if ($this->policy === null) {
      throw new InvalidArgumentException(
        'Query requires a post-type policy. Call postType(), postTypes(), or anyPostType().',
      );
    }

    return new Definition(
      policy: $this->policy,
      allowedPostTypes: $this->allowedPostTypes,
      unlimited: $this->unlimited,
      maxPosts: $this->maxPosts,
      defaultLimit: $this->defaultLimit,
      hasLimitField: $this->hasLimitField,
      hasOrderingField: $this->hasOrderingField,
      orderbyChoices: $this->orderbyChoices,
      defaultOrderby: $this->defaultOrderby,
      taxonomies: $this->taxonomies,
      taxonomyLabels: $this->taxonomyLabels,
      postStatus: $this->postStatuses,
      into: $this->into,
      mapper: $this->mapper,
      injector: $this->injector,
      resolver: $this->resolver,
    );
  }

  private function assertPolicyNotSet(): void
  {
    if ($this->policy !== null) {
      throw new InvalidArgumentException(
        'Query post-type policy is already set. Call only one of postType(), postTypes(), or anyPostType().',
      );
    }
  }

  private function assertPostTypeSlug(string $slug): string
  {
    $slug = trim($slug);
    if (!QueryRequest::isPostTypeSlug($slug)) {
      throw new InvalidArgumentException("Invalid post type [{$slug}].");
    }

    return $slug;
  }

  private function assertTaxonomySlug(string $slug): string
  {
    $slug = trim($slug);
    if (!QueryRequest::isTaxonomySlug($slug)) {
      throw new InvalidArgumentException("Invalid taxonomy [{$slug}].");
    }

    return $slug;
  }

  /**
   * @param list<string>|array<string, string> $choices
   * @return array<string, string>
   */
  private function normalizeOrderbyChoices(array $choices): array
  {
    $normalized = [];

    foreach ($choices as $key => $value) {
      if (is_int($key)) {
        $orderby = AllowedOrderBy::normalizeKey((string) $value);
        if ($orderby === null) {
          throw new InvalidArgumentException("Invalid orderby [{$value}].");
        }
        $normalized[$orderby] = AllowedOrderBy::label($orderby);
        continue;
      }

      $orderby = AllowedOrderBy::normalizeKey((string) $key);
      if ($orderby === null) {
        throw new InvalidArgumentException("Invalid orderby [{$key}].");
      }
      $normalized[$orderby] = (string) $value;
    }

    return $normalized;
  }

  /**
   * @param array<string, string>|list<string> $orderby
   * @return array<string, string>
   */
  private function normalizeOrderby(array $orderby): array
  {
    if ($orderby === []) {
      throw new InvalidArgumentException('Query orderby cannot be empty.');
    }

    $normalized = [];

    foreach ($orderby as $key => $value) {
      if (is_int($key)) {
        $orderbyKey = AllowedOrderBy::normalizeKey((string) $value);
        if ($orderbyKey === null) {
          throw new InvalidArgumentException("Invalid orderby [{$value}].");
        }
        $normalized[$orderbyKey] = 'DESC';
        continue;
      }

      $orderbyKey = AllowedOrderBy::normalizeKey((string) $key);
      if ($orderbyKey === null) {
        throw new InvalidArgumentException("Invalid orderby [{$key}].");
      }

      $direction = strtoupper((string) $value);
      if ($orderbyKey !== 'rand' && $direction !== 'ASC' && $direction !== 'DESC') {
        throw new InvalidArgumentException("Invalid order direction [{$value}].");
      }

      $normalized[$orderbyKey] = $orderbyKey === 'rand' ? 'DESC' : $direction;
    }

    return $normalized;
  }
}
