<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Storage;

/**
 * Storage location for ACF fields stored in WP Options
 */
class OptionsStorage extends AbstractStorageLocation
{
  /**
   * Constructor
   */
  public function __construct(string $optionPrefix = 'options')
  {
    $this->fieldPrefix = $optionPrefix;
  }

  /**
   * Read a field value from options
   */
  public function read(string $fieldName, bool $isKeyRef = false, ?string $value = null): mixed
  {
    $optionName = $this->buildFieldName($fieldName, $isKeyRef);
    return get_option($optionName, false);
  }

  /**
   * Update a field value to options
   */
  public function update(string $oldFieldName, string $newFieldName, mixed $newFieldValue, bool $isKeyRef = false): int
  {
    if ($this->isDryRun()) {
      return 1;
    }

    $optionName = $this->buildFieldName($newFieldName, $isKeyRef);
    update_option($optionName, $newFieldValue);
    return 1;
  }

  /**
   * Delete a field from options
   */
  public function delete(string $fieldName, bool $isKeyRef = false): int
  {
    if ($this->isDryRun()) {
      return 1;
    }

    $optionName = $this->buildFieldName($fieldName, $isKeyRef);
    delete_option($optionName);
    return 1;
  }

  /**
   * Find fields matching a pattern
   */
  public function findFields(string $pattern): array
  {
    global $wpdb;

    // $fullPattern = $this->buildFieldName($pattern, $isKeyRef);

    $query = $wpdb->prepare(
      "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
      $pattern
    );

    return $wpdb->get_col($query);
  }

  /**
   * Get the storage type name
   */
  public function getStorageType(): string
  {
    return 'options';
  }
}