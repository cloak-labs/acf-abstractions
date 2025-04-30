<?php

namespace CloakWP\ACF\Console\Migrations;

use CloakWP\ACF\Database\Migrations\{MigrationInterface, MigrationFinderInterface, MigratorInterface};
use CloakWP\ACF\Database\Migrations\{MigrationFinder, Migrator};
use CloakWP\ACF\Database\Migrations\History\NullMigrationHistory;
use CloakWP\ACF\Database\Migrations\Results\MigrationResult;
use WP_CLI;

class MigrateCommand
{
  /**
   * The migration migrator instance
   */
  protected MigratorInterface $migrator;
  protected MigrationFinderInterface $migrationFinder;

  /**
   * Whether this is a dry run
   */
  protected $isDryRun = false;

  /**
   * Whether this is a multisite migration
   */
  protected $multisite = false;

  /**
   * Whether to ignore the migration history
   */
  protected $ignoreHistory = false;

  /**
   * The operation type (migrate or rollback)
   */
  protected $operationType = 'migration';
  protected $operationPastTense = 'Migrated';

  /**
   * Verbosity level
   */
  protected $verbosity = 1;

  /**
   * Run pending ACF field migrations
   * 
   * ## OPTIONS
   * 
   * [--path=<path>]
   * : Custom path to migrations directory (default: wp-content/acf-migrations)
   * 
   * [--name=<name>]
   * : Run a specific migration by name
   * 
   * [--backup]
   * : Create a database backup before migrating
   * 
   * [--backup-dir=<path>]
   * : Directory to store database backups (default: wp-content/backups)
   * 
   * [--network]
   * : Run the migration for all sites in a multisite network
   * 
   * [--dry-run]
   * : Show what migrations would run without actually running them
   * 
   * [--verbose]
   * : Show detailed information about changes
   * 
   * [--quiet]
   * : Show minimal output
   * 
   * [--ignore-history]
   * : Ignore the migration history and run migration(s) even if they have already been run
   */
  public function __invoke($args, $assoc_args)
  {
    try {
      // Parse common arguments
      $path = $assoc_args['path'] ?? WP_CONTENT_DIR . '/acf-migrations';
      $name = $assoc_args['name'] ?? null;
      $createBackup = isset($assoc_args['backup']);
      $backupDir = $assoc_args['backup-dir'] ?? WP_CONTENT_DIR . '/backups';
      $this->isDryRun = isset($assoc_args['dry-run']);
      $this->multisite = isset($assoc_args['network']);
      $this->ignoreHistory = isset($assoc_args['ignore-history']);

      // Set verbosity level
      if (isset($assoc_args['verbose'])) {
        $this->verbosity = 2;
      } elseif (isset($assoc_args['quiet'])) {
        $this->verbosity = 0;
      }

      // Initialize the migrator
      $this->initMigrator();
      $this->initMigrationFinder($path);

      // Show dry run message if needed
      $this->showDryRunMessage();

      // Create backup if requested
      if ($createBackup) {
        $this->createDatabaseBackup($backupDir);
      }

      // Process the command
      if ($name) {
        $this->processSingleMigration($name);
      } else {
        $this->processAllMigrations();
      }
    } catch (\Exception $e) {
      WP_CLI::error("An error occurred: {$e->getMessage()}");
    }
  }

  /**
   * Initialize the migration migrator
   */
  protected function initMigrator(): void
  {
    if ($this->ignoreHistory) {
      $this->migrator = new Migrator(migrationHistory: new NullMigrationHistory());
    } else {
      $this->migrator = new Migrator();
    }

    $this->migrator
      ->setMultisite($this->multisite)
      ->setDryRun($this->isDryRun);
  }

  /**
   * Initialize the migration migrator
   */
  protected function initMigrationFinder(string $path): void
  {
    $this->migrationFinder = new MigrationFinder($path);
  }

  /**
   * Show dry run message if needed
   */
  protected function showDryRunMessage(): void
  {
    if ($this->isDryRun && $this->verbosity > 0) {
      WP_CLI::line("Running in dry run mode - no database changes will be made");
    }
  }

  /**
   * Process a single migration file
   */
  protected function processSingleMigration(string $name): void
  {
    $migration = $this->migrationFinder->findMigrationByName($name);

    // Check if the migration exists
    if (!$migration) {
      WP_CLI::warning("Migration '{$name}' not found.");
      return;
    }

    $this->processMigrationOrArray($migration, $name);
  }

  /**
   * Process all migrations
   */
  protected function processAllMigrations(): void
  {
    // Get migrations
    $migrations = $this->migrationFinder->findMigrations();

    if (empty($migrations)) {
      WP_CLI::success("No migrations found.");
      return;
    }

    foreach ($migrations as $name => $migration) {
      $this->processMigrationOrArray($migration, $name);
    }
  }

  /**
   * Process a migration or array of migrations
   * 
   * @param MigrationInterface|MigrationInterface[] $migrationOrArray
   * @param string $name The migration name or file name
   */
  protected function processMigrationOrArray(mixed $migrationOrArray, string $name): void
  {
    // Handle array of migrations
    if (is_array($migrationOrArray)) {
      if ($this->verbosity > 0) {
        WP_CLI::line("\nFound multiple migrations in file '{$name}'");
      }

      foreach ($migrationOrArray as $index => $migration) {
        if ($this->verbosity > 0) {
          $migrationName = $migration->getName();
          $number = $index + 1;
          WP_CLI::line("\n--- Running migration {$number}: {$migrationName} ---");
        }
        $this->runMigration($migration);
      }
    } else {
      // Run single migration
      $this->runMigration($migrationOrArray);
    }
  }

  protected function runMigration(MigrationInterface $migration): MigrationResult
  {
    $name = $migration->getName();

    if ($this->verbosity > 0) {
      WP_CLI::line("\nRunning migration: {$name}");
    }

    $result = $this->migrator->run($migration);

    $this->displayResult($name, $result);

    return $result;
  }

  /**
   * Display the result of a migration
   */
  protected function displayResult(string $name, MigrationResult $result): void
  {
    if ($this->multisite) {
      if (!$result->isSuccess()) {
        $this->displaySingleMigrationResult($name, $result);
        return;
      }

      $totalChanges = 0;
      foreach ($result->getSiteResults() as $site => $siteResult) {
        $totalChanges += $siteResult->getTotalCount();
      }

      if ($this->verbosity > 0) {
        WP_CLI::line("\n=====================================================");
        if ($this->isDryRun) {
          WP_CLI::line("\033[1mSummary:\033[0m Would make {$totalChanges} changes across all subsites.");
        } else {
          WP_CLI::line("\033[1mSummary:\033[0m Made {$totalChanges} changes across all subsites.");
        }
        WP_CLI::line("=====================================================\n");
      }

      foreach ($result->getSiteResults() as $site => $siteResult) {
        if ($this->verbosity > 0) {
          WP_CLI::line("\n========================================================================");
          WP_CLI::line("Subsite '{$site}':");
          WP_CLI::line("========================================================================\n");
        }
        $this->displaySingleMigrationResult($name, $siteResult);
      }
    } else {
      $this->displaySingleMigrationResult($name, $result);
    }
  }

  /**
   * Display the result of a single migration
   */
  protected function displaySingleMigrationResult(string $name, MigrationResult $result): void
  {
    if ($result->isSuccess()) {
      $changeCount = $result->getTotalCount();
      $storageResults = $result->getStorageResults();
      $fieldChanges = $result->getFieldChanges();

      if ($this->verbosity > 0) {
        if ($result->isDryRun()) {
          WP_CLI::success("Dry run of {$this->operationType} '{$name}' would make {$changeCount} changes:");
        } else {
          WP_CLI::success("{$this->getOperationPastTense()} '{$name}' successfully with {$changeCount} changes:");
        }
      } else {
        // Quiet mode - just show minimal success message
        if ($result->isDryRun()) {
          WP_CLI::success("Dry run: {$changeCount} changes would be made");
        } else {
          WP_CLI::success("{$changeCount} changes made");
        }
        return;
      }

      // Display storage results
      if (!empty($storageResults) && $this->verbosity > 0) {
        WP_CLI::line("\nData changes by storage type:");
        WP_CLI::line("*****************************");
        foreach ($storageResults as $type => $count) {
          WP_CLI::line(" - {$type}: {$count}");
        }
      }

      // Display field changes
      if (!empty($fieldChanges) && $this->verbosity > 0) {
        WP_CLI::line("\nField changes:");
        WP_CLI::line("**************\n");

        $i = 0;
        foreach ($fieldChanges as $fieldKey => $data) {
          // Add separator between fields (except before the first one)
          if ($i > 0)
            WP_CLI::line("------------------------------------------------");
          $i++;

          // Display main field changes
          $this->displayFieldChange($data);

          // Display sub-field changes if any
          if (!empty($data['sub_fields'])) {
            WP_CLI::line("\n  Sub-fields:");

            foreach ($data['sub_fields'] as $subFieldKey => $subFieldData) {
              $this->displayFieldChange($subFieldData);
            }
          }
        }
      }
    } else {
      WP_CLI::warning("Failed to run {$this->operationType} '{$name}': {$result->getMessage()}");
    }
  }

  protected function displayFieldChange(array $fieldData): void
  {
    // Print field's old name and type as a comment before listing its changes
    WP_CLI::line("\033[1m{$fieldData['name']}\033[0m (type: {$fieldData['field_type']})");

    foreach ($fieldData['changes'] as $property => $values) {
      $oldValue = $values['old_value'];
      $newValue = $values['new_value'];
      $affectedRows = $values['affected_rows'];

      // Skip if no change
      if ($oldValue === $newValue) {
        continue;
      }

      // Capitalize and bold the property name
      $propertyLabel = ucfirst($property);
      $rowsText = $affectedRows > 1 ? " ({$affectedRows} rows)" : "";
      WP_CLI::line(" - \033[1m{$propertyLabel}:\033[0m {$oldValue} \033[1m-->\033[0m {$newValue}{$rowsText}");
    }
  }

  /**
   * Get the past tense of the operation
   */
  protected function getOperationPastTense(bool $lowercase = false): string
  {
    return $lowercase ? strtolower($this->operationPastTense) : $this->operationPastTense;
  }

  /**
   * Create a database backup using WP-CLI
   */
  protected function createDatabaseBackup(string $backupDir): void
  {
    if ($this->isDryRun)
      return;

    // Ensure backup directory exists
    if (!is_dir($backupDir)) {
      if (!mkdir($backupDir, 0755, true)) {
        WP_CLI::error("Failed to create backup directory: {$backupDir} ... aborting {$this->operationType}.");
        return;
      }
    }

    // Generate backup filename with timestamp
    $timestamp = date('Y-m-d-His');
    $backupFile = "{$backupDir}/acf-{$this->operationType}-backup-{$timestamp}.sql";

    if ($this->verbosity > 0) {
      WP_CLI::line("Creating database backup...");
    }

    // Use WP-CLI to export the database
    $command = "db export {$backupFile} --porcelain";
    $result = WP_CLI::runcommand($command, [
      'return' => true,
    ]);

    if ($result) {
      if ($this->verbosity > 0) {
        WP_CLI::success("Database backup created: {$backupFile}");
      }
    } else {
      WP_CLI::error("Failed to create database backup. Aborting {$this->operationType}.");
    }
  }
}
