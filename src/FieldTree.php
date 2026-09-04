<?php

declare(strict_types=1);

namespace CloakWP\ACF;

use Extended\ACF\Fields\Field;
use InvalidArgumentException;

final class FieldTree
{
  /**
   * Find a nested sub-field by dotted ACF name (`footer.cta`).
   *
   * @throws InvalidArgumentException
   */
  public static function find(Field $root, string $path): Field
  {
    $current = $root;

    foreach (explode('.', $path) as $segment) {
      $segment = trim($segment);
      if ($segment === '') {
        throw new InvalidArgumentException('Field path contains an empty segment.');
      }

      $found = null;
      foreach ($current->settings['sub_fields'] ?? [] as $sub) {
        if ($sub instanceof Field && ($sub->settings['name'] ?? '') === $segment) {
          $found = $sub;
          break;
        }
      }

      if (!$found instanceof Field) {
        $from = $current->settings['name'] ?? $current::class;
        throw new InvalidArgumentException("Field [{$segment}] not found on [{$from}].");
      }

      $current = $found;
    }

    return $current;
  }
}
