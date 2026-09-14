<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class AllowedOrderBy
{
  /** @var list<string> */
  public const KEYS = [
    'date',
    'title',
    'name',
    'menu_order',
    'modified',
    'ID',
    'author',
    'rand',
    'parent',
  ];

  public const LABELS = [
    'date' => 'Date',
    'title' => 'Title',
    'name' => 'Slug',
    'menu_order' => 'Menu order',
    'modified' => 'Last modified',
    'ID' => 'ID',
    'author' => 'Author',
    'rand' => 'Random',
    'parent' => 'Parent',
  ];

  public static function normalizeKey(string $key): ?string
  {
    if (strcasecmp($key, 'id') === 0) {
      return 'ID';
    }

    return in_array($key, self::KEYS, true) ? $key : null;
  }

  public static function label(string $key): string
  {
    $normalized = self::normalizeKey($key) ?? $key;

    return self::LABELS[$normalized] ?? $normalized;
  }
}
