<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\History;

use CloakWP\ACF\Database\Migrations\MigrationInterface;

/**
 * Interface for tracking migration history. This is essentially a Repository for migration CRUD operations.
 * The idea is that the Migrator will use this to track which migrations have been run, and by keeping it separate
 * we can make it easier to swap out the storage engine.
 */
interface MigrationHistoryInterface
{
  /**
   * Check if a migration has been run
   */
  public function hasRun(string $migrationName): bool;

  /**
   * Add a migration to history
   */
  public function add(MigrationInterface $migration): bool;

  /**
   * Remove a migration from history
   */
  public function remove(string $migrationName): bool;

  /**
   * Get all migrations that have been run
   */
  public function getAll(): array;

  /**
   * Get the most recent migrations
   */
  public function getRecent(int $limit = 10): array;
}