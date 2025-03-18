<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\Storage\StorageLocationInterface;

/**
 * Represents an ACF field migration
 */
class Migration extends AbstractMigration
{
  /**
   * Field value transformers indexed by field name
   * 
   * @var array<string, callable>
   */
  protected array $fieldValueTransformers = [];

  /**
   * Generate a rollback migration that reverses the changes made by this migration
   */
  public function generateRollback(): RollbackMigration
  {
    return RollbackMigration::from($this);
  }

  /**
   * Register a field value transformer function that will be applied during migration
   * 
   * @param string $fieldName The field name to apply the transformer to
   * @param callable $transformer Function that receives the old value and returns the transformed value
   * @return static
   */
  public function transformFieldValue(string $fieldName, callable $transformer): static
  {
    $this->fieldValueTransformers[$fieldName] = $transformer;
    return $this;
  }

  /**
   * Get all registered field value transformers
   * 
   * @return array<string, callable> Array of field transformers indexed by field name
   */
  public function getFieldValueTransformers(): array
  {
    return $this->fieldValueTransformers;
  }
}