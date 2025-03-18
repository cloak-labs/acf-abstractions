<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Migrators;

use CloakWP\ACF\Database\Migrations\Fields\FieldMapping;
use CloakWP\ACF\Database\Migrations\Storage\StorageLocationInterface;

class RepeaterFieldMigrator extends DefaultFieldMigrator
{
  /**
   * Migrate a repeater field and its sub-fields
   */
  public function migrateField(
    FieldMapping $fieldMapping,
    StorageLocationInterface $storage,
  ): void {
    // First handle the base field like a normal field
    parent::migrateField($fieldMapping, $storage);

    // Only migrate sub-fields if something has changed
    if ($fieldMapping->hasChanged()) {
      $this->migrateRepeaterSubFields($fieldMapping, $storage);
    }
  }

  /**
   * Migrate repeater sub-fields
   */
  protected function migrateRepeaterSubFields(
    FieldMapping $fieldMapping,
    StorageLocationInterface $storage,
  ): void {
    // Find all fields that match the pattern for repeater sub-fields
    // This pattern matches fields like "field_name_0_subfield", "field_name_1_subfield", etc.
    $pattern = $fieldMapping->getOldName() . '_[0-9]+_%';
    $repeaterFields = $storage->findFields($pattern);

    // Also get direct children for row count fields
    $directChildrenPattern = $fieldMapping->getOldName() . '_[0-9]+$';
    $directChildren = $storage->findFields($directChildrenPattern);

    // Combine all fields to process
    $allFields = array_merge($repeaterFields, $directChildren);

    foreach ($allFields as $field) {
      // Get the field name
      $fieldName = is_object($field) ? $field->meta_key : $field;

      // Skip the base field which was already handled
      if ($fieldName === $fieldMapping->getOldName()) {
        continue;
      }

      // Convert the field name using the mapping
      $newSubFieldName = $fieldMapping->convertSubFieldName($fieldName);

      // Skip if no transformation occurred
      if ($newSubFieldName === $fieldName) {
        continue;
      }

      // Migrate both the meta key reference and field value in one go
      $this->migrateFieldData(
        $fieldMapping,
        $storage,
        $fieldName,
        $newSubFieldName
      );
    }
  }
}