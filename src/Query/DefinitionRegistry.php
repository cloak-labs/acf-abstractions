<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

use RuntimeException;

final class DefinitionRegistry
{
  /** @var array<string, Definition> */
  private static array $definitions = [];

  public static function put(Definition $definition): string
  {
    $id = 'query_' . uniqid('', true);
    self::$definitions[$id] = $definition;

    return $id;
  }

  public static function get(string $id): ?Definition
  {
    return self::$definitions[$id] ?? null;
  }

  public static function require(string $id): Definition
  {
    $definition = self::get($id);

    if ($definition === null) {
      throw new RuntimeException("Unknown Query definition [{$id}].");
    }

    return $definition;
  }

  public static function reset(): void
  {
    self::$definitions = [];
  }
}
