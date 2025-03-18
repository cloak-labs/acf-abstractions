<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\Fields\Migrators\FieldMigratorInterface;
use CloakWP\ACF\Database\Migrations\History\MigrationHistoryInterface;
use CloakWP\ACF\Database\Migrations\Results\MigrationResult;

/**
 * Interface for ACF Migrators. A migrator is responsible for running a migration.
 */
interface MigratorInterface
{
  /**
   * Set dry run mode
   */
  public function setDryRun(bool $isDryRun): static;

  /**
   * Set multisite mode
   */
  public function setMultisite(bool $multisite): static;

  /**
   * Register a field migrator for a specific field type
   */
  public function registerFieldMigrator(string $fieldType, FieldMigratorInterface $migrator): static;

  /**
   * Run a migration
   */
  public function run(MigrationInterface $migration): MigrationResult;

  /**
   * Run a dry run of a migration
   */
  public function dryRun(MigrationInterface $migration): MigrationResult;

  /**
   * Set the migration history tracker
   */
  public function setMigrationHistory(MigrationHistoryInterface $history): static;

  /**
   * Get the migration history tracker
   */
  public function getMigrationHistory(): MigrationHistoryInterface;

  /**
   * Get the migration
   */
  public function getMigration(): MigrationInterface;
}
