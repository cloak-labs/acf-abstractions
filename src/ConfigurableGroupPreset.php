<?php

namespace CloakWP\ACF;

use Extended\ACF\Fields\Field;
use Extended\ACF\Fields\Group;
use InvalidArgumentException;

abstract class ConfigurableGroupPreset extends Group
{
  /**
   * @var array<string, Field>
   */
  protected array $baseFields = [];

  /**
   * @var array<string, array{field: Field, position: string, relativeTo: string}>
   */
  protected array $customFields = [];

  /**
   * @var array<string>
   */
  protected array $hiddenFields = [];

  abstract protected function defaultFields(): array;


  public function __construct(string $label, string|null $name = null)
  {
    parent::__construct($label, $name);
    $this->baseFields = $this->defaultFields();
    $this->fields($this->getFields());
  }

  /**
   * @internal
   * @deprecated This method is for internal use only. Do not call directly, as this defeats the purpose of the "ConfigurableGroupPreset"class. Use the "addField" method instead.
   * @param array<Field> $fields
   */
  public function fields(array $fields): static
  {
    parent::fields($fields);
    return $this;
  }

  /**
   * Hide specific fields from the final output
   *
   * @param string|array<string> $keys Field keys to hide
   */
  public function hideFields(string|array $keys): self
  {
    $keys = is_string($keys) ? [$keys] : $keys;
    $this->hiddenFields = array_merge($this->hiddenFields, $keys);
    $this->fields($this->getFields()); // Using fields instead of fields
    return $this;
  }

  /**
   * Add a custom field at a specific position
   *
   * @param Field $field The field to add
   * @param string $position 'before' or 'after'
   * @param string $relativeTo The key of the existing field to position relative to
   */
  public function addField(Field $field, string $position, string $relativeTo): self
  {
    if (!isset($this->baseFields[$relativeTo])) {
      throw new InvalidArgumentException("Field '{$relativeTo}' does not exist in base fields");
    }

    $property = (new \ReflectionClass($field))->getProperty('settings');
    $property->setAccessible(true);
    $settings = $field->settings;

    $this->customFields[$settings['name']] = [
      'field' => $field,
      'position' => $position,
      'relativeTo' => $relativeTo
    ];

    $this->fields($this->getFields()); // Using fields instead of fields
    return $this;
  }

  /**
   * Get all fields in their final order with customizations applied
   *
   * @return array<Field>
   */
  protected function getFields(): array
  {
    $fields = [];

    // Start with base fields, excluding hidden ones
    foreach ($this->baseFields as $key => $field) {
      if (in_array($key, $this->hiddenFields)) {
        continue;
      }

      // Add any custom fields that should go before this field
      foreach ($this->customFields as $customKey => $customData) {
        if ($customData['relativeTo'] === $key && $customData['position'] === 'before') {
          $fields[] = $customData['field'];
          unset($this->customFields[$customKey]);
        }
      }

      $fields[] = $field;

      // Add any custom fields that should go after this field
      foreach ($this->customFields as $customKey => $customData) {
        if ($customData['relativeTo'] === $key && $customData['position'] === 'after') {
          $fields[] = $customData['field'];
          unset($this->customFields[$customKey]);
        }
      }
    }

    return $fields;
  }
}