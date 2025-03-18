<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\Fields\{Field, FieldMapping};

/**
 * Data Transfer Object for resolved migration data
 */
class ResolvedMigration
{
  /**
   * Constructor
   */
  public function __construct(
    protected MigrationInterface $migration,
    protected array $oldFields,
    protected array $newFields,
    protected array $fieldMappings
  ) {
  }

  /**
   * Get original Migration instance
   */
  public function getMigration(): MigrationInterface
  {
    return $this->migration;
  }

  /**
   * Get old fields
   * 
   * @return Field[]
   */
  public function getOldFields(): array
  {
    return $this->oldFields;
  }

  /**
   * Get new fields
   * 
   * @return Field[]
   */
  public function getNewFields(): array
  {
    return $this->newFields;
  }

  /**
   * Get field mappings
   * 
   * @return FieldMapping[]
   */
  public function getFieldMappings(): array
  {
    return $this->fieldMappings;
  }

  /**
   * Get old field by key
   */
  public function getOldField(string $key): ?Field
  {
    return $this->oldFields[$key] ?? null;
  }

  /**
   * Get new field by key
   */
  public function getNewField(string $key): ?Field
  {
    return $this->newFields[$key] ?? null;
  }

  /**
   * Get old field name by key
   */
  public function getOldFieldName(string $key): string
  {
    return $this->oldFields[$key]['full_name'] ?? '';
  }

  /**
   * Get new field name by key
   */
  public function getNewFieldName(string $key): string
  {
    return $this->newFields[$key]['full_name'] ?? '';
  }
}