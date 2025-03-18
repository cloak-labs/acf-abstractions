<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Resolvers;

/**
 * Resolver for CloakWP FieldGroup objects, meant to be registered as a fieldCollectionResolver in a MigrationMappingsResolver.
 */
class FieldGroupResolver implements FieldResolverInterface
{
  public function supports($fields): bool
  {
    return is_object($fields) &&
      method_exists($fields, 'get') &&
      class_exists('\\CloakWP\\ACF\\FieldGroup') &&
      $fields instanceof \CloakWP\ACF\FieldGroup;
  }

  public function resolve($fields): array
  {
    self::purgeKeys();
    return $fields->get()['fields'];
  }

  /**
   * Purges ACF keys stored in the \Extended\ACF\Key class.
   * Useful to run between resolving old/new fields to prevent key collision errors.
   */
  public static function purgeKeys(): void
  {
    if (class_exists('\\Extended\\ACF\\Key')) {
      $reflection = new \ReflectionClass(\Extended\ACF\Key::class);
      $keysProperty = $reflection->getProperty('keys');
      $keysProperty->setAccessible(true);
      $keysProperty->setValue([]);
    }
  }
}