<?php

namespace CloakWP\ACF\Console\Migrations;

use WP_CLI;

class RollbackCommand extends MigrateCommand
{
  protected $operationType = 'rollback';
  protected $operationPastTense = 'Rolled back';
  protected $steps = 1;

  /**
   * Rollback ACF field migrations
   * 
   * ## OPTIONS
   * 
   * [--steps=<number>]
   * : Number of migrations to roll back (default: 1)
   * 
   * [--path=<path>]
   * : Custom path to migrations directory (default: wp-content/acf-migrations)
   * 
   * [--name=<name>]
   * : Roll back a specific migration by name
   * 
   * [--backup]
   * : Create a database backup before rolling back
   * 
   * [--backup-dir=<path>]
   * : Directory to store database backups (default: wp-content/backups)
   * 
   * [--network]
   * : Run the migration for all sites in a multisite network
   * 
   * [--dry-run]
   * : Show what migrations would be rolled back without actually rolling them back
   */
  public function __invoke($args, $assoc_args)
  {
    // Add steps parameter for rollback
    $this->steps = (int) ($assoc_args['steps'] ?? 1);

    // Call parent implementation
    parent::__invoke($args, $assoc_args);
  }

  /**
   * Process all migrations
   */
  protected function processAllMigrations(): void
  {
    // Get completed migrations from history
    $completedMigrations = $this->migrator->getMigrationHistory()->getRecent($this->steps);

    if (empty($completedMigrations)) {
      WP_CLI::success("No migrations to roll back.");
      return;
    }

    $results = [];

    // Process each completed migration
    foreach ($completedMigrations as $migrationData) {
      $name = $migrationData['name'];

      // Skip rollback migrations
      if (strpos($name, 'rollback_') === 0) {
        continue;
      }

      // Find the original migration
      $migration = $this->migrationFinder->findMigrationByName($name);

      if (!$migration) {
        WP_CLI::warning("Migration file for '{$name}' not found. Skipping rollback.");
        continue;
      }

      // Create rollback migration
      $rollbackMigration = $migration->generateRollback();

      // Run the rollback
      $results[$name] = $this->migrator->run($rollbackMigration);

      // Stop on first failure
      if (!$results[$name]->isSuccess()) {
        break;
      }
    }

    // Display results
    if (empty($results)) {
      WP_CLI::success("No migrations were rolled back.");
      return;
    }

    foreach ($results as $name => $result) {
      $this->displayResult($name, $result);
    }
  }

  /**
   * Process a single migration
   */
  protected function processSingleMigration(string $name): void
  {
    // Check if the migration exists in history
    // if (!$this->migrator->getMigrationHistory()->hasRun($name)) {
    //   WP_CLI::warning("Migration '{$name}' has not been run, so it cannot be rolled back.");
    //   return;
    // }

    // Find the original migration
    $migration = $this->migrationFinder->findMigrationByName($name);

    if (!$migration) {
      WP_CLI::warning("Migration file for '{$name}' not found.");
      return;
    }

    // Create rollback migration
    $rollbackMigration = $migration->generateRollback();

    // Run the rollback
    $result = $this->migrator->run($rollbackMigration);

    // Display the result
    $this->displayResult($name, $result);
  }
}