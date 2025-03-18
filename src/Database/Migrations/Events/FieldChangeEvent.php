<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Events;

use CloakWP\ACF\Database\Migrations\Fields\FieldMapping;

/**
 * Event for field changes
 */
class FieldChangeEvent extends Event
{
  /**
   * The field mapping
   */
  protected FieldMapping $fieldMapping;

  /**
   * The property that changed
   */
  protected string $property;

  /**
   * The value before the change
   */
  protected string $oldValue;

  /**
   * The value after the change
   */
  protected string $newValue;

  /**
   * The storage type where the change occurred
   */
  protected string $storageType;

  /**
   * The parent field key (for sub-fields)
   */
  protected ?string $parentFieldKey;

  /**
   * The number of rows affected by this change
   */
  protected int $affectedRows;

  /**
   * Constructor
   */
  public function __construct(
    FieldMapping $fieldMapping,
    string $property,
    string $oldValue,
    string $newValue,
    string $storageType,
    int $affectedRows = 1,
    ?string $parentFieldKey = null
  ) {
    $this->fieldMapping = $fieldMapping;
    $this->property = $property;
    $this->oldValue = $oldValue;
    $this->newValue = $newValue;
    $this->storageType = $storageType;
    $this->affectedRows = $affectedRows;
    $this->parentFieldKey = $parentFieldKey;
  }

  /**
   * Get the field mapping
   */
  public function getFieldMapping(): FieldMapping
  {
    return $this->fieldMapping;
  }

  /**
   * Get the field type
   */
  public function getFieldType(): string
  {
    return $this->fieldMapping->getFieldType();
  }

  /**
   * Get the field key
   */
  public function getFieldKey(): string
  {
    return $this->fieldMapping->getOldKey();
  }

  /**
   * Get the property that changed
   */
  public function getProperty(): string
  {
    return $this->property;
  }

  /**
   * Get the value before the change
   */
  public function getOldValue(): string
  {
    return $this->oldValue;
  }

  /**
   * Get the value after the change
   */
  public function getNewValue(): string
  {
    return $this->newValue;
  }

  /**
   * Get the storage type
   */
  public function getStorageType(): string
  {
    return $this->storageType;
  }

  /**
   * Get the number of rows affected
   */
  public function getAffectedRows(): int
  {
    return $this->affectedRows;
  }

  /**
   * Get the parent field key
   */
  public function getParentFieldKey(): ?string
  {
    return $this->parentFieldKey;
  }

  /**
   * Check if this is a sub-field
   */
  public function isSubField(): bool
  {
    return $this->parentFieldKey !== null;
  }

  /**
   * Get the event type
   */
  public function getType(): string
  {
    return 'field_change';
  }
}