<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

/**
 * Recursively finds marked Query groups in an ACF field-definition tree.
 */
final class QueryDiscovery
{
  /**
   * @param array<string, mixed> $data Parsed block field values.
   * @param list<array<string, mixed>>|array<string, array<string, mixed>> $fieldDefinitions
   * @return list<array{id: string, path: list<string|int>, field: array<string, mixed>, value: mixed}>
   */
  public static function find(array $data, array $fieldDefinitions): array
  {
    $found = [];
    self::walk($data, $fieldDefinitions, [], $found);

    return $found;
  }

  /**
   * @param array<string, mixed>|list<mixed> $value
   * @param list<array<string, mixed>>|array<string, array<string, mixed>> $fields
   * @param list<string|int> $path
   * @param list<array{id: string, path: list<string|int>, field: array<string, mixed>, value: mixed}> $found
   */
  private static function walk(mixed $value, array $fields, array $path, array &$found): void
  {
    foreach (self::asList($fields) as $field) {
      if (!is_array($field)) {
        continue;
      }

      $name = $field['name'] ?? null;
      if (!is_string($name) || $name === '') {
        continue;
      }

      $type = (string) ($field['type'] ?? '');
      $currentPath = [...$path, $name];
      $currentValue = is_array($value) && array_key_exists($name, $value) ? $value[$name] : null;

      $queryId = $field['cloakwp_query_id'] ?? null;
      if (is_string($queryId) && $queryId !== '') {
        $found[] = [
          'id' => $queryId,
          'path' => $currentPath,
          'field' => $field,
          'value' => $currentValue,
        ];
        continue;
      }

      if ($type === 'repeater') {
        $rows = is_array($currentValue) ? array_values($currentValue) : [];
        foreach ($rows as $index => $row) {
          self::walk(
            is_array($row) ? $row : [],
            $field['sub_fields'] ?? [],
            [...$currentPath, $index],
            $found,
          );
        }
        continue;
      }

      if ($type === 'flexible_content') {
        $rows = is_array($currentValue) ? array_values($currentValue) : [];
        foreach ($rows as $index => $row) {
          if (!is_array($row)) {
            continue;
          }
          $layoutFields = self::layoutSubFields($field, $row['acf_fc_layout'] ?? null);
          self::walk($row, $layoutFields, [...$currentPath, $index], $found);
        }
        continue;
      }

      if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
        self::walk(
          is_array($currentValue) ? $currentValue : [],
          $field['sub_fields'],
          $currentPath,
          $found,
        );
      }
    }
  }

  /**
   * @param array<string, mixed> $field
   * @return list<array<string, mixed>>
   */
  private static function layoutSubFields(array $field, mixed $layoutName): array
  {
    if (!is_string($layoutName) || $layoutName === '') {
      return [];
    }

    foreach ($field['layouts'] ?? [] as $layout) {
      if (!is_array($layout)) {
        continue;
      }
      if (($layout['name'] ?? '') === $layoutName) {
        return is_array($layout['sub_fields'] ?? null) ? $layout['sub_fields'] : [];
      }
    }

    return [];
  }

  /**
   * @param list<array<string, mixed>>|array<string, array<string, mixed>> $fields
   * @return list<array<string, mixed>>
   */
  private static function asList(array $fields): array
  {
    if ($fields === []) {
      return [];
    }

    return array_is_list($fields) ? $fields : array_values($fields);
  }
}
