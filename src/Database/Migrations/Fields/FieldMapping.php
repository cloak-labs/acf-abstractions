<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields;

/**
 * Represents a mapping between an old field and a new field
 */
class FieldMapping
{
  /**
   * Constructor
   */
  public function __construct(
    protected Field $oldField,
    protected Field $newField
  ) {
  }

  /**
   * Get the old field
   */
  public function getOldField(): Field
  {
    return $this->oldField;
  }

  /**
   * Get the new field
   */
  public function getNewField(): Field
  {
    return $this->newField;
  }

  /**
   * Get the old field key
   */
  public function getOldKey(): string
  {
    return $this->oldField->getKey();
  }

  /**
   * Get the new field key
   */
  public function getNewKey(): string
  {
    return $this->newField->getKey();
  }

  /**
   * Get the old field name
   */
  public function getOldName(): string
  {
    return $this->oldField->getFullName();
  }

  /**
   * Get the new field name
   */
  public function getNewName(): string
  {
    return $this->newField->getFullName();
  }

  /**
   * Get the field type
   */
  public function getFieldType(): string
  {
    return $this->oldField->getType();
  }

  /**
   * Check if the field key has changed
   */
  public function hasKeyChanged(): bool
  {
    return $this->getOldKey() !== $this->getNewKey();
  }

  /**
   * Check if the field name has changed
   */
  public function hasNameChanged(): bool
  {
    return $this->getOldName() !== $this->getNewName();
  }

  /**
   * Check if any aspect of the field has changed
   */
  public function hasChanged(): bool
  {
    return $this->hasKeyChanged() || $this->hasNameChanged();
  }

  /**
   * Check if this is a specific field type
   */
  public function isFieldType(string $type): bool
  {
    return $this->getFieldType() === $type;
  }

  /**
   * Get the field name prefix for sub-fields
   * 
   * @param bool $useNewName Whether to use the new name (true) or old name (false)
   * @return string The field name prefix
   */
  public function getFieldNamePrefix(bool $useNewName = false): string
  {
    return $useNewName ? $this->getNewName() . '_' : $this->getOldName() . '_';
  }

  /**
   * Convert a sub-field name from the old field structure to the new field structure
   * 
   * For example, if old field name is "team" and new field name is "staff",
   * this will convert "team_0_member" to "staff_0_member"
   * 
   * @param string $subFieldName The sub-field name in the old structure
   * @return string The equivalent sub-field name in the new structure, or original name if not a sub-field
   */
  public function convertSubFieldName(string $subFieldName): string
  {
    $oldPrefix = $this->getFieldNamePrefix(false);

    if (strpos($subFieldName, $oldPrefix) === 0) {
      $newPrefix = $this->getFieldNamePrefix(true);
      $subFieldSuffix = substr($subFieldName, strlen($oldPrefix));
      return $newPrefix . $subFieldSuffix;
    }

    return $subFieldName;
  }
}