<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\History;

use CloakWP\ACF\Database\Migrations\MigrationInterface;

/**
 * A migration history implementation that doesn't track anything
 * Useful for allowing migrations to run multiple times or for testing
 */
class NullMigrationHistory implements MigrationHistoryInterface
{
  /**
   * In-memory storage for the current session only
   * This doesn't persist between requests
   */
  private array $migrations = [];

  /**
   * Check if a migration has been run
   * Always returns false to allow migrations to run
   */
  public function hasRun(string $migrationName): bool
  {
    return false;
  }

  /**
   * Add a migration to history
   * Only stores in memory for the current request
   */
  public function add(MigrationInterface $migration): bool
  {
    $this->migrations[$migration->getName()] = [
      'name' => $migration->getName(),
      'description' => $migration->getDescription(),
      'run_at' => date('Y-m-d H:i:s')
    ];

    return true;
  }

  /**
   * Remove a migration from history
   */
  public function remove(string $migrationName): bool
  {
    if (isset($this->migrations[$migrationName])) {
      unset($this->migrations[$migrationName]);
    }

    return true;
  }

  /**
   * Get all migrations that have been run
   * Only returns migrations run in the current request
   */
  public function getAll(): array
  {
    return array_values($this->migrations);
  }

  /**
   * Get the most recent migrations
   * Only returns migrations run in the current request
   */
  public function getRecent(int $limit = 10): array
  {
    return array_slice(array_values($this->migrations), 0, $limit);
  }
}