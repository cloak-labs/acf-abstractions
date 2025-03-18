<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\History;

use CloakWP\ACF\Database\Migrations\MigrationInterface;

/**
 * Tracks migration history in WordPress options table
 * Useful for simpler setups that don't want to create a custom table (see DatabaseMigrationHistory)
 */
class OptionsMigrationHistory implements MigrationHistoryInterface
{
  /**
   * Option name for storing migration history
   */
  private string $optionName;

  /**
   * Constructor
   */
  public function __construct(string $optionName = 'acf_migration_history')
  {
    $this->optionName = $optionName;
  }

  /**
   * Get all stored migrations
   */
  private function getMigrations(): array
  {
    $migrations = get_option($this->optionName, []);
    return is_array($migrations) ? $migrations : [];
  }

  /**
   * Save migrations to the options table
   */
  private function saveMigrations(array $migrations): bool
  {
    return update_option($this->optionName, $migrations);
  }

  /**
   * Check if a migration has been run
   */
  public function hasRun(string $migrationName): bool
  {
    $migrations = $this->getMigrations();
    return isset($migrations[$migrationName]);
  }

  /**
   * Add a migration to history
   */
  public function add(MigrationInterface $migration): bool
  {
    $migrations = $this->getMigrations();

    $migrations[$migration->getName()] = [
      'name' => $migration->getName(),
      'description' => $migration->getDescription(),
      'batch' => count($migrations) + 1,
      'run_at' => date('Y-m-d H:i:s')
    ];

    return $this->saveMigrations($migrations);
  }

  /**
   * Remove a migration from history
   */
  public function remove(string $migrationName): bool
  {
    $migrations = $this->getMigrations();

    if (isset($migrations[$migrationName])) {
      unset($migrations[$migrationName]);
      return $this->saveMigrations($migrations);
    }

    return true;
  }

  /**
   * Get all migrations that have been run
   */
  public function getAll(): array
  {
    $migrations = $this->getMigrations();
    return array_values($migrations);
  }

  /**
   * Get the most recent migrations
   */
  public function getRecent(int $limit = 10): array
  {
    $migrations = $this->getMigrations();

    // Sort by run_at in descending order
    usort($migrations, function ($a, $b) {
      return strcmp($b['run_at'], $a['run_at']);
    });

    return array_slice(array_values($migrations), 0, $limit);
  }
}