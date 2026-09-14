<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\Query;

use CloakWP\ACF\Fields\Query;
use InvalidArgumentException;

final class ConfigurationTest extends QueryTestCase
{
  public function testRequiresAPostTypePolicy(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Query requires a post-type policy');

    Query::make()->toArray('group_missing');
  }

  public function testPostTypePoliciesAreMutuallyExclusive(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('already set');

    Query::make()->postType('team')->anyPostType();
  }

  public function testIntoAndInjectUsingAreMutuallyExclusive(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('into() and injectUsing()');

    Query::make()
      ->postType('team')
      ->into('members')
      ->injectUsing(fn(array $block, array $items) => $block);
  }

  public function testRejectsEmptyPostTypes(): void
  {
    $this->expectException(InvalidArgumentException::class);
    Query::make()->postTypes([]);
  }

  public function testRejectsInvalidOrderby(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid orderby');

    Query::make()->postType('post')->withOrdering(['not_a_column']);
  }

  public function testRejectsNonPositiveLimit(): void
  {
    $this->expectException(InvalidArgumentException::class);
    Query::make()->postType('post')->withLimit(0);
  }
}
