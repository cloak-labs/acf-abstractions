<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Migrators;

use CloakWP\ACF\Database\Migrations\Events\EventDispatcher;
use CloakWP\ACF\Database\Migrations\Events\FieldChangeEvent;
use CloakWP\ACF\Database\Migrations\Fields\FieldMapping;
use CloakWP\ACF\Database\Migrations\MigratorInterface;
use CloakWP\ACF\Database\Migrations\Storage\StorageLocationInterface;

abstract class AbstractFieldMigrator implements FieldMigratorInterface
{
  /**
   * Reference to the parent migrator
   */
  protected MigratorInterface $migrator;

  /**
   * Event dispatcher
   */
  protected EventDispatcher $eventDispatcher;

  /**
   * Constructor
   */
  public function __construct(MigratorInterface $migrator, EventDispatcher $eventDispatcher)
  {
    $this->migrator = $migrator;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Migrate a field using the provided field mapping
   */
  abstract public function migrateField(
    FieldMapping $fieldMapping,
    StorageLocationInterface $storage,
  ): void;

  /**
   * Migrate a field's data using a field mapping
   * This handles both the meta key reference and the field value
   * 
   * @param FieldMapping $fieldMapping The field mapping to use
   * @param StorageLocationInterface $storage The storage location
   * @param string|null $customOldName Optional custom old field name (for sub-fields)
   * @param string|null $customNewName Optional custom new field name (for sub-fields)
   * @param string|null $parentFieldKey Optional parent field key (for sub-fields)
   * @return bool Whether any changes were made
   */
  protected function migrateFieldData(
    FieldMapping $fieldMapping,
    StorageLocationInterface $storage,
    ?string $customOldName = null,
    ?string $customNewName = null,
    ?string $parentFieldKey = null
  ): bool {
    $oldName = $customOldName ?? $fieldMapping->getOldName();
    $newName = $customNewName ?? $fieldMapping->getNewName();
    $changesMade = false;

    // Update the key reference (meta key) if keys have changed
    if ($fieldMapping->hasKeyChanged()) {
      $existingKeyValue = $storage->read(
        $oldName,
        true,
        $fieldMapping->getOldKey()
      );

      if ($existingKeyValue !== false) {
        // Write the new key value and track affected rows
        $affectedRows = $storage->update(
          $oldName,
          $newName,
          $fieldMapping->getNewKey(),
          true,
        );

        if ($oldName !== $newName) {
          $storage->delete($oldName, true);
        }

        $changesMade = true;

        // Dispatch field change event for key with affected row count
        $this->eventDispatcher->dispatch(new FieldChangeEvent(
          $fieldMapping,
          'key',
          $fieldMapping->getOldKey(),
          $fieldMapping->getNewKey(),
          $storage->getStorageType(),
          $affectedRows,
          $parentFieldKey
        ));
      }
    }

    // Update the field value if names are different or if there's a user-provided value transformer
    $transformers = $this->migrator->getMigration()->getFieldValueTransformers();
    $hasTransformer = isset($transformers[$oldName]) || isset($transformers[$newName]);

    if ($oldName !== $newName || $hasTransformer) {
      $oldFieldValue = $storage->read($oldName);

      if ($oldFieldValue !== false) {
        $newFieldValue = $oldFieldValue;

        // Apply transformer if one exists for this field
        if (isset($transformers[$oldName])) {
          $newFieldValue = $transformers[$oldName]($oldFieldValue);
        } elseif (isset($transformers[$newName])) {
          $newFieldValue = $transformers[$newName]($oldFieldValue);
        }

        // Write the field value to the new name and track affected rows
        $affectedRows = $storage->update($oldName, $newName, $newFieldValue);

        if ($oldName !== $newName) {
          $storage->delete($oldName);
        }

        $changesMade = true;

        // Dispatch field change event for name with affected row count
        $this->eventDispatcher->dispatch(new FieldChangeEvent(
          $fieldMapping,
          'name',
          $oldName,
          $newName,
          $storage->getStorageType(),
          $affectedRows,
          $parentFieldKey
        ));

        if ($hasTransformer) {
          // Dispatch field change event for value with affected row count
          $this->eventDispatcher->dispatch(new FieldChangeEvent(
            $fieldMapping,
            'value',
            $oldFieldValue,
            $newFieldValue,
            $storage->getStorageType(),
            $affectedRows,
            $parentFieldKey
          ));
        }
      }
    }

    return $changesMade;
  }
}