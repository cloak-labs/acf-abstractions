<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\Query;

use CloakWP\ACF\Query\DefinitionRegistry;
use Extended\ACF\Key;
use PHPUnit\Framework\TestCase;

abstract class QueryTestCase extends TestCase
{
  protected function setUp(): void
  {
    DefinitionRegistry::reset();

    $keys = new \ReflectionProperty(Key::class, 'keys');
    $keys->setValue(null, []);
  }

  /**
   * @param list<array<string, mixed>> $fields
   * @return list<string>
   */
  protected function fieldNames(array $fields): array
  {
    $names = [];
    foreach ($fields as $field) {
      if (is_array($field) && isset($field['name']) && $field['name'] !== '') {
        $names[] = $field['name'];
      }
    }

    return $names;
  }

  protected function findField(array $fields, string $name): ?array
  {
    foreach ($fields as $field) {
      if (is_array($field) && ($field['name'] ?? null) === $name) {
        return $field;
      }
    }

    return null;
  }
}
