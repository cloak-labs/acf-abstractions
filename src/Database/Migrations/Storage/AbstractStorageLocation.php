<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Storage;

/**
 * Abstract base class for storage locations
 */
abstract class AbstractStorageLocation implements StorageLocationInterface
{
  /**
   * Whether this is a dry run
   */
  protected bool $isDryRun = false;

  /**
   * Optional prefix for all field names in this storage location
   */
  protected string $fieldPrefix = '';

  /**
   * Set dry run mode
   */
  public function setDryRun(bool $isDryRun): void
  {
    $this->isDryRun = $isDryRun;
  }

  /**
   * Check if this is a dry run
   */
  public function isDryRun(): bool
  {
    return $this->isDryRun;
  }

  /**
   * Build the full field name
   */
  protected function buildFieldName(string $fieldName, bool $isKeyRef = false): string
  {
    $prefix = $isKeyRef ? '_' : '';

    if ($this->fieldPrefix) {
      $prefix .= $this->fieldPrefix . '_';
    }

    return $prefix . $fieldName;
  }
}