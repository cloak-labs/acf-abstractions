<?php

declare(strict_types=1);

namespace CloakWP\ACF\Query;

final class Bootstrap
{
  private static bool $booted = false;
  private static ?BlockEnricher $enricher = null;

  public static function boot(): void
  {
    if (self::$booted) {
      return;
    }

    self::$booted = true;

    add_filter('cloakwp/block/data', [self::class, 'enrich'], 10, 4);
  }

  /**
   * @param array<string, mixed> $parsedBlock
   * @param list<array<string, mixed>>|array<string, array<string, mixed>> $fieldDefinitions
   * @return array<string, mixed>
   */
  public static function enrich(array $parsedBlock, array $fieldDefinitions, mixed $block, mixed $postId): array
  {
    $id = is_numeric($postId) ? (int) $postId : null;

    return self::enricher()->enrich($parsedBlock, $fieldDefinitions, $block, $id);
  }

  public static function usingEnricher(BlockEnricher $enricher): void
  {
    self::$enricher = $enricher;
  }

  public static function reset(): void
  {
    self::$enricher = null;
    DefinitionRegistry::reset();
  }

  private static function enricher(): BlockEnricher
  {
    return self::$enricher ??= new BlockEnricher(
      new MemoizedQueryExecutor(new WordPressQueryExecutor()),
    );
  }
}
