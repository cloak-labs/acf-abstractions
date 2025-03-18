<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Storage;

/**
 * Interface for ACF field storage locations
 */
interface StorageLocationInterface
{
  /**
   * Read a field value from storage
   */
  public function read(string $fieldName, bool $isKeyRef = false, ?string $value = null): mixed;

  /**
   * Update an existing field in storage
   * 
   * @return int Number of rows affected
   */
  public function update(string $oldFieldName, string $newFieldName, mixed $newFieldValue, bool $isKeyRef = false): int;

  /**
   * Delete a field from storage
   * 
   * @return int Number of rows affected
   */
  public function delete(string $fieldName, bool $isKeyRef = false): int;

  /**
   * Find fields matching a pattern
   */
  public function findFields(string $pattern): array;

  /**
   * Get the storage type name for results tracking
   */
  public function getStorageType(): string;

  /**
   * Set the dry run mode
   */
  public function setDryRun(bool $isDryRun): void;

  /**
   * Check if this is a dry run
   */
  public function isDryRun(): bool;
}