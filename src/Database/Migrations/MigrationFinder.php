<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

/**
 * Finds ACF field migrations in a directory
 */
class MigrationFinder implements MigrationFinderInterface
{
  /**
   * Path to migration files
   */
  private string $migrationsPath;

  public function __construct(string $migrationsPath)
  {
    $this->migrationsPath = rtrim($migrationsPath, '/');
  }

  /**
   * Finds, sorts, and returns all Migration instances in the migrations directory
   * 
   * @return MigrationInterface[]
   */
  public function findMigrations(): array
  {
    if (!is_dir($this->migrationsPath)) {
      return [];
    }

    $migrations = [];
    $files = glob($this->migrationsPath . '/*.php');

    // Sort migration files alphabetically (timestamps in filenames ensure they're in chronological order)
    sort($files);

    foreach ($files as $file) {
      $migration = $this->loadMigrationFromFile($file);

      if ($migration instanceof MigrationInterface) {
        $migrations[$migration->getName()] = $migration;
      }
    }

    return $migrations;
  }

  public function findMigrationByName(string $name): ?MigrationInterface
  {
    $migrations = $this->findMigrations();
    return $migrations[$name] ?? null;
  }

  /**
   * Load a migration from a file
   * 
   * @param string $file The file path
   * @return MigrationInterface|MigrationInterface[]|null
   */
  private function loadMigrationFromFile(string $file): MigrationInterface|array|null
  {
    // Use include instead of require to avoid fatal errors
    $migration = include $file;

    if ($migration instanceof MigrationInterface) {
      return $migration;
    }

    // Handle array of migrations
    if (is_array($migration)) {
      return array_filter($migration, fn($m) => $m instanceof MigrationInterface);
    }

    return null;
  }
}