<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Traits;

use CloakWP\ACF\Database\Migrations\Fields\Migrators\FieldMigratorInterface;

trait RegistersFieldMigrators
{
  /**
   * Field migrators
   */
  protected array $fieldMigrators = [];

  /**
   * Register a field migrator
   */
  public function registerFieldMigrator(string $fieldType, FieldMigratorInterface $migrator): static
  {
    $this->fieldMigrators[$fieldType] = $migrator;
    return $this;
  }

  /**
   * Get a field migrator for a specific field type
   */
  public function getFieldMigrator(string $fieldType): FieldMigratorInterface
  {
    return $this->fieldMigrators[$fieldType] ?? $this->fieldMigrators['default'];
  }
}