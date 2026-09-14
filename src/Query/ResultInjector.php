<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class ResultInjector
{
  /**
   * @param array<string, mixed> $parsedBlock
   * @param list<mixed> $items
   * @param list<string|int> $path
   * @return array<string, mixed>
   */
  public static function inject(
    array $parsedBlock,
    array $items,
    array $path,
    Definition $definition,
    QueryContext $context,
  ): array {
    if (is_callable($definition->injector)) {
      $result = ($definition->injector)($parsedBlock, $items, $context);

      return is_array($result) ? $result : $parsedBlock;
    }

    if (!isset($parsedBlock['data']) || !is_array($parsedBlock['data'])) {
      $parsedBlock['data'] = [];
    }

    if (is_string($definition->into) && $definition->into !== '') {
      $parsedBlock['data'][$definition->into] = $items;

      return $parsedBlock;
    }

    $parsedBlock['data'] = self::setNested($parsedBlock['data'], [...$path, 'results'], $items);

    return $parsedBlock;
  }

  /**
   * @param array<string|int, mixed> $target
   * @param list<string|int> $path
   * @return array<string|int, mixed>
   */
  public static function setNested(array $target, array $path, mixed $value): array
  {
    if ($path === []) {
      return $target;
    }

    $cursor = &$target;
    $last = array_key_last($path);

    foreach ($path as $index => $segment) {
      if ($index === $last) {
        $cursor[$segment] = $value;
        break;
      }

      if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
        $cursor[$segment] = [];
      }

      $cursor = &$cursor[$segment];
    }

    unset($cursor);

    return $target;
  }
}
