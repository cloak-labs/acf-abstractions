<?php

namespace CloakWP\ACF\Database\Migrations;

class RollbackMigration extends AbstractMigration
{
  protected string $originalName = '';

  public static function getRollbackName(string $name): string
  {
    return 'rollback_' . $name;
  }

  /**
   * Create a rollback migration from an original migration
   */
  public static function from(Migration $migration): self
  {
    $rollback = new self();
    $originalName = $migration->getName();
    $rollback->name = self::getRollbackName($originalName);
    $rollback->originalName = $originalName;

    // Swap before/after fields
    $rollback->oldFields = $migration->getNewFields();
    $rollback->newFields = $migration->getOldFields();

    // Reverse name changes
    $rollback->nameChanges = array_flip($migration->getNameChanges());

    // Copy storage locations
    foreach ($migration->getStorageLocations() as $location) {
      $rollback->storageLocations[] = $location;
    }

    // Set description
    $rollback->description = 'Rollback of: ' . $migration->getDescription();

    return $rollback;
  }

  /**
   * Get the original migration name
   */
  public function getOriginalName(): string
  {
    return $this->originalName;
  }
}