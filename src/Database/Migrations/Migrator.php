<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\Traits\RegistersFieldMigrators;
use CloakWP\ACF\Database\Migrations\History\{MigrationHistoryInterface, DatabaseMigrationHistory};
use CloakWP\ACF\Database\Migrations\Fields\Migrators\{FieldMigratorInterface, DefaultFieldMigrator, RepeaterFieldMigrator};
use CloakWP\ACF\Database\Migrations\Results\MigrationResult;
use CloakWP\ACF\Database\Migrations\Events\EventDispatcher;

/**
 * Executes ACF field migrations
 */
class Migrator implements MigratorInterface
{
  use RegistersFieldMigrators;

  /**
   * Field processor
   */
  protected MigrationMappingsResolverInterface $fieldsResolver;

  /**
   * Migration history tracker
   */
  protected MigrationHistoryInterface $migrationHistory;

  /**
   * Event dispatcher
   */
  protected EventDispatcher $eventDispatcher;

  /**
   * Holds the current Migration object
   */
  protected MigrationInterface $migration;

  /**
   * Whether this is a dry run
   */
  protected bool $isDryRun = false;
  protected bool $isValidated = false;
  protected bool $multisite = false;
  // protected ResolvedMigration $resolvedMigration;

  /**
   * Field migrators by field type
   * 
   * @var array<string, FieldMigratorInterface>
   */
  protected array $fieldMigrators = [];

  /**
   * Constructor
   */
  public function __construct(
    MigrationMappingsResolverInterface $fieldsResolver = null,
    MigrationHistoryInterface $migrationHistory = null,
    EventDispatcher $eventDispatcher = null
  ) {
    $this->fieldsResolver = $fieldsResolver ?? new MigrationMappingsResolver();
    $this->migrationHistory = $migrationHistory ?? new DatabaseMigrationHistory();
    $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
    $this->registerDefaultFieldMigrators();
  }

  /**
   * Set dry run mode
   */
  public function setDryRun(bool $isDryRun): static
  {
    $this->isDryRun = $isDryRun;
    return $this;
  }

  /**
   * Set multisite mode
   */
  public function setMultisite(bool $isMultisite): static
  {
    $this->multisite = $isMultisite;
    return $this;
  }

  /**
   * Set the migration history tracker
   */
  public function setMigrationHistory(MigrationHistoryInterface $history): static
  {
    $this->migrationHistory = $history;
    return $this;
  }

  /**
   * Get the migration history tracker
   */
  public function getMigrationHistory(): MigrationHistoryInterface
  {
    return $this->migrationHistory;
  }

  /**
   * Validate a migration
   * 
   * @throws \Exception If the migration is invalid
   */
  protected function validateMigration(): void
  {
    if (!$this->migration) {
      throw new \Exception('No migration object set.');
    }

    // Validate that we have before & after field structures
    if (empty($this->migration->getOldFields()) || empty($this->migration->getNewFields())) {
      throw new \Exception('Both before and after field structures must be provided');
    }

    // Validate that we have registered storage locations
    if (empty($this->migration->getStorageLocations())) {
      throw new \Exception('No storage locations registered');
    }

    // Check migration history
    if ($this->migration instanceof RollbackMigration) {
      $originalName = $this->migration->getOriginalName();
      if (!$this->migrationHistory->hasRun($originalName)) {
        throw new \Exception("Cannot rollback migration as it has not yet been run (or it was already rolled back).");
      }

      // Check if this rollback has already been run
      $rollbackName = $this->migration->getName();
      if ($this->migrationHistory->hasRun($rollbackName)) {
        throw new \Exception("Rollback has already been run.");
      }
    } else {
      // For regular migrations, check if it has already run
      if ($this->migrationHistory->hasRun($this->migration->getName())) {
        throw new \Exception("Migration has already been run.");
      }
    }
  }

  /**
   * Process a migration
   */
  protected function resolveMigration(): ResolvedMigration
  {
    return $this->fieldsResolver->resolve($this->migration);
  }

  /**
   * Run a migration
   */
  public function run(MigrationInterface $migration): MigrationResult
  {
    $this->migration = $migration;
    $result = new MigrationResult($this->isDryRun);

    try {
      // Validate and process the migration
      $this->validateMigration();
      $resolvedMigration = $this->resolveMigration();

      if (!$resolvedMigration) {
        throw new \Exception('Failed to resolve migration before running it. Double-check you have configured it correctly.');
      }

      // Clear any existing observers and register the result as an observer
      $this->eventDispatcher->clearObservers();
      $this->eventDispatcher->addObserver($result);

      // Run the migration based on multisite setting
      if ($this->multisite) {
        $this->runMultisiteMigration($resolvedMigration, $result);
      } else {
        $this->runSingleSiteMigration($resolvedMigration, $result);
      }

    } catch (\Exception $e) {
      $result->setSuccess(false)->setMessage($e->getMessage());
    }

    return $result;
  }

  /**
   * Run a dry run of a migration
   */
  public function dryRun(MigrationInterface $migration): MigrationResult
  {
    $originalDryRun = $this->isDryRun;
    $this->setDryRun(true);
    $result = $this->run($migration);
    $this->setDryRun($originalDryRun);
    return $result;
  }

  /**
   * Run a migration for all sites in a multisite network
   */
  private function runMultisiteMigration(ResolvedMigration $resolvedMigration, MigrationResult $result): void
  {
    if (!is_multisite()) {
      $result->setSuccess(false)->setMessage('This is not a multisite installation.');
      return;
    }

    $sites = get_sites();

    foreach ($sites as $site) {
      switch_to_blog((int) $site->blog_id);

      $siteResult = new MigrationResult($this->isDryRun);

      // Clear previous observers and add only the current site result
      $this->eventDispatcher->clearObservers();
      $this->eventDispatcher->addObserver($siteResult);

      try {
        // Use runSingleSiteMigration for each site to benefit from transaction handling
        $this->runSingleSiteMigration($resolvedMigration, $siteResult);
        $result->addSiteResult($site->path, $siteResult);
      } catch (\Exception $e) {
        $siteResult->setSuccess(false)->setMessage($e->getMessage());
        $result->setSuccess(false)->setMessage("Failed on site {$site->path}: {$e->getMessage()}");
      }

      restore_current_blog();
    }

    // Restore the main result as an observer after all sites are processed
    $this->eventDispatcher->clearObservers();
    $this->eventDispatcher->addObserver($result);
  }

  /**
   * Run a migration for a single site
   */
  private function runSingleSiteMigration(ResolvedMigration $resolvedMigration, MigrationResult $result): void
  {
    // Start transaction
    global $wpdb;
    if (!$this->isDryRun) {
      $wpdb->query('START TRANSACTION');
    }

    try {
      $this->executeMigration($resolvedMigration);

      // Update migration history
      if (!$this->isDryRun) {
        $this->updateMigrationHistory();
        $wpdb->query('COMMIT');
      }
    } catch (\Exception $e) {
      // Rollback transaction
      if (!$this->isDryRun) {
        $wpdb->query('ROLLBACK');
      }

      $result->setSuccess(false)->setMessage($e->getMessage());
    }
  }

  /**
   * Execute the migration using storage locations
   */
  private function executeMigration(ResolvedMigration $resolvedMigration): void
  {
    foreach ($resolvedMigration->getMigration()->getStorageLocations() as $storage) {
      $storage->setDryRun($this->isDryRun);

      foreach ($resolvedMigration->getFieldMappings() as $fieldMapping) {
        $this->getFieldMigrator($fieldMapping->getFieldType())?->migrateField($fieldMapping, $storage);
      }
    }
  }

  /**
   * Update migration history after successful execution
   */
  private function updateMigrationHistory(): void
  {
    if ($this->migration instanceof RollbackMigration) {
      // Remove the original migration from history
      $this->migrationHistory->remove($this->migration->getOriginalName());

      // Add the rollback to history to prevent running it again
      $this->migrationHistory->add($this->migration);
    } else {
      // For regular migrations, check if there's a rollback record and remove it
      $rollbackName = RollbackMigration::getRollbackName($this->migration->getName());
      if ($this->migrationHistory->hasRun($rollbackName)) {
        $this->migrationHistory->remove($rollbackName);
      }

      // Add the migration to history
      $this->migrationHistory->add($this->migration);
    }
  }

  /**
   * Register default field migrators
   */
  protected function registerDefaultFieldMigrators(): void
  {
    $this->registerFieldMigrator('default', new DefaultFieldMigrator($this, $this->eventDispatcher));
    $this->registerFieldMigrator('repeater', new RepeaterFieldMigrator($this, $this->eventDispatcher));
    $this->registerFieldMigrator('flexible_content', new RepeaterFieldMigrator($this, $this->eventDispatcher));
  }

  /**
   * Get the migration
   */
  public function getMigration(): MigrationInterface
  {
    return $this->migration;
  }
}