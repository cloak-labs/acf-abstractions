<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\FieldGroup;
use CloakWP\ACF\Database\Migrations\Fields\Migrators\FieldMigratorInterface;
use CloakWP\ACF\Database\Migrations\Storage\StorageLocationInterface;

/**
 * Interface for ACF field data migrators
 */
interface MigrationInterface
{
  /**
   * Optionally set the migration description
   */
  public function description(string $description): static;

  /**
   * Set the before (old) field structure. 
   * By default, you can pass in an array of ACF field settings defined as:
   *    (1) standard array format,
   *    (2) ExtendedACF field objects,
   *    (3) CloakWP FieldGroup objects (with attached fields),
   *    (4) or as any other custom format as long as you register a field "type" and/or "collection" resolver that handles converting it to the standard ACF array format. 
   */
  public function before(FieldGroup|array $fields): static;

  /**
   * Set the after (new) field structure. 
   * By default, you can pass in an array of ACF field settings defined as:
   *    (1) standard array format,
   *    (2) ExtendedACF field objects,
   *    (3) CloakWP FieldGroup objects (with attached fields),
   *    (4) or as any other custom format as long as you register a field "type" and/or "collection" resolver that handles converting it to the standard ACF array format. 
   */
  public function after(FieldGroup|array $fields): static;

  /**
   * Set explicit name changes for fields
   */
  public function nameChanges(array $changes): static;

  /**
   * Register a storage location location (i.e. a location where the existing ACF fields are stored and that
   * must be migrated, such as wp_postmeta (PostMetaStorage), wp_options (OptionsStorage), 
   * or Gutenberg blocks (BlocksStorage))
   */
  public function forStorageLocation(StorageLocationInterface $location): static;

  /**
   * Register a field value transformer function that will be applied during migration
   * 
   * @param string $fieldName The field name to apply the transformer to
   * @param callable $transformer Function that receives the old value and returns the transformed value
   * @return static
   */
  public function transformFieldValue(string $fieldName, callable $transformer): static;

  /**
   * Get all registered field value transformers
   * 
   * @return array<string, callable> Array of field transformers indexed by field name
   */
  public function getFieldValueTransformers(): array;

  // Getters
  public function getName(): string;
  public function getNameChanges(): array;
  public function getDescription(): string;
  public function getOldFields(): FieldGroup|array;
  public function getNewFields(): FieldGroup|array;
  public function getStorageLocations(): array;
  // public function getFieldMigrators(): array;

  // public function registerFieldMigrator(string $type, FieldMigratorInterface $migrator): static;
  // public function setDryRun(bool $isDryRun): static;
  // public function run(): array;
  // public function dryRun(): array;
  // public function generateRollback(): static;
}
