<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Resolvers;

/**
 * Adapter for raw ACF field arrays
 */
class DefaultFieldResolver implements FieldResolverInterface
{
  public function supports($field): bool
  {
    return is_array($field) && isset($field['key']) && isset($field['name']);
  }

  public function resolve($field): array
  {
    return $field;
  }
}