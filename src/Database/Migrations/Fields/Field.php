<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields;

/**
 * Represents an ACF field with its metadata
 */
class Field
{
  /**
   * Field data
   */
  protected array $data;

  /**
   * Constructor
   */
  public function __construct(array $fieldData)
  {
    if (!isset($fieldData['key']) || !isset($fieldData['name'])) {
      throw new \InvalidArgumentException('Field must have a key and name');
    }

    $this->data = $fieldData;

    // Ensure full_name is set
    if (!isset($this->data['full_name'])) {
      $this->data['full_name'] = $this->data['name'];
    }
  }

  /**
   * Get field key
   */
  public function getKey(): string
  {
    return $this->data['key'];
  }

  /**
   * Get field name
   */
  public function getName(): string
  {
    return $this->data['name'];
  }

  /**
   * Get full field name (including parent prefixes)
   */
  public function getFullName(): string
  {
    return $this->data['full_name'];
  }

  /**
   * Get field type
   */
  public function getType(): string
  {
    return $this->data['type'] ?? 'text';
  }

  /**
   * Get all field data
   */
  public function getData(): array
  {
    return $this->data;
  }

  /**
   * Get a specific field property
   */
  public function get(string $property, mixed $default = null): mixed
  {
    return $this->data[$property] ?? $default;
  }

  /**
   * Check if field has sub-fields
   */
  public function hasSubFields(): bool
  {
    return !empty($this->data['sub_fields']);
  }

  /**
   * Check if field has layouts (for flexible content)
   */
  public function hasLayouts(): bool
  {
    return !empty($this->data['layouts']);
  }

  /**
   * Check if field has changed compared to another field
   */
  public function hasChangedFrom(Field $otherField): bool
  {
    return $this->getFullName() !== $otherField->getFullName() ||
      $this->getKey() !== $otherField->getKey();
  }

  /**
   * Create a field from array data
   */
  public static function fromArray(array $data): self
  {
    return new self($data);
  }
}