<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Resolvers;

/**
 * Adapter for Extended ACF fields (see https://github.com/vinkla/extended-acf/)
 */
class ExtendedAcfFieldResolver implements FieldResolverInterface
{
  public function supports($field): bool
  {
    return is_object($field) &&
      method_exists($field, 'get') &&
      class_exists('\\Extended\\ACF\\Fields\\Field') &&
      $field instanceof \Extended\ACF\Fields\Field;
  }

  public function resolve($field): array
  {
    // FieldGroupResolver::purgeKeys(); // TODO: consider purging keys here as well.. currently only purges if users provides fields wrapped by FieldGroup (see FieldGroupResolver)
    return $field->get();
  }
}