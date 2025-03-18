<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Resolvers;

/**
 * Adapter for raw array of ACF fields
 */
class DefaultFieldCollectionResolver implements FieldResolverInterface
{
  public function supports($fields): bool
  {
    return is_array($fields);
  }

  public function resolve($fields): array
  {
    return $fields;
  }
}