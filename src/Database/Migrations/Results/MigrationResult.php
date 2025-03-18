<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Results;

use CloakWP\ACF\Database\Migrations\Events\{Event, FieldChangeEvent};
use CloakWP\ACF\Database\Migrations\Observers\EventObserver;

/**
 * Contains the results of a migration. Implements the Obeserver Pattern 
 * to track field changes.
 */
class MigrationResult implements EventObserver
{
  /**
   * Whether the migration was successful
   */
  protected bool $success = true;

  /**
   * Error message if the migration failed
   */
  protected ?string $message = null;

  /**
   * Whether this was a dry run
   */
  protected bool $isDryRun = false;

  /**
   * Storage results by type
   * 
   * @var array<string, int>
   */
  protected array $storageResults = [];

  /**
   * Field changes organized by field
   * 
   * @var array<string, array{
   *   field_type: string,
   *   changes: array<string, array{old_value: string, new_value: string, affected_rows: int}>,
   *   sub_fields: array<string, array{
   *     changes: array<string, array{old_value: string, new_value: string, affected_rows: int}>
   *   }>
   * }>
   */
  protected array $fieldChanges = [];

  /**
   * Site-specific results for multisite
   * 
   * @var array<string, MigrationResult>
   */
  protected array $siteResults = [];

  /**
   * Constructor
   */
  public function __construct(bool $isDryRun = false)
  {
    $this->isDryRun = $isDryRun;
  }

  /**
   * Create a successful result
   */
  public static function success(bool $isDryRun = false): self
  {
    return new self($isDryRun);
  }

  /**
   * Create an error result
   */
  public static function error(string $message, bool $isDryRun = false): self
  {
    $result = new self($isDryRun);
    $result->setSuccess(false);
    $result->setMessage($message);
    return $result;
  }

  /**
   * Set the success status
   */
  public function setSuccess(bool $success): self
  {
    $this->success = $success;
    return $this;
  }

  /**
   * Set the error message
   */
  public function setMessage(string $message): self
  {
    $this->message = $message;
    return $this;
  }

  /**
   * Add a site result
   */
  public function addSiteResult(string $site, MigrationResult $result): self
  {
    $this->siteResults[$site] = $result;
    return $this;
  }

  /**
   * Handle an event
   */
  public function onEvent(Event $event): void
  {
    if ($event instanceof FieldChangeEvent) {
      $this->handleFieldChangeEvent($event);
    }
  }

  /**
   * Handle a field change event
   */
  private function handleFieldChangeEvent(FieldChangeEvent $event): void
  {
    $fieldMapping = $event->getFieldMapping();
    $fieldKey = $fieldMapping->getOldKey();
    $fieldName = $fieldMapping->getOldName();
    $fieldType = $fieldMapping->getFieldType();
    $property = $event->getProperty();
    $oldValue = $event->getOldValue();
    $newValue = $event->getNewValue();
    $storageType = $event->getStorageType();
    $isSubField = $event->isSubField();
    $parentFieldKey = $event->getParentFieldKey();
    $affectedRows = $event->getAffectedRows();

    // Track storage operation
    if (!isset($this->storageResults[$storageType])) {
      $this->storageResults[$storageType] = 0;
    }
    $this->storageResults[$storageType] += $affectedRows;

    // Track field changes
    if ($isSubField && $parentFieldKey) {
      // Handle sub-field change
      $this->initializeFieldIfNeeded($parentFieldKey, $fieldType, $fieldName);

      if (!isset($this->fieldChanges[$parentFieldKey]['sub_fields'][$fieldKey])) {
        $this->fieldChanges[$parentFieldKey]['sub_fields'][$fieldKey] = [
          'changes' => []
        ];
      }

      $this->fieldChanges[$parentFieldKey]['sub_fields'][$fieldKey]['changes'][$property] = [
        'old_value' => $oldValue,
        'new_value' => $newValue,
        'affected_rows' => $affectedRows
      ];
    } else {
      // Handle main field change
      $this->initializeFieldIfNeeded($fieldKey, $fieldType, $fieldName);

      $this->fieldChanges[$fieldKey]['changes'][$property] = [
        'old_value' => $oldValue,
        'new_value' => $newValue,
        'affected_rows' => $affectedRows
      ];
    }
  }

  /**
   * Initialize a field in the changes array if it doesn't exist
   */
  private function initializeFieldIfNeeded(string $fieldKey, string $fieldType, ?string $fieldName = null): void
  {
    if (!isset($this->fieldChanges[$fieldKey])) {
      $this->fieldChanges[$fieldKey] = [
        'name' => $fieldName,
        'field_type' => $fieldType,
        'changes' => [],
        'sub_fields' => []
      ];
    }
  }

  /**
   * Check if the migration was successful
   */
  public function isSuccess(): bool
  {
    return $this->success;
  }

  /**
   * Get the error message
   */
  public function getMessage(): ?string
  {
    return $this->message;
  }

  /**
   * Check if this was a dry run
   */
  public function isDryRun(): bool
  {
    return $this->isDryRun;
  }

  /**
   * Get storage results
   */
  public function getStorageResults(): array
  {
    return $this->storageResults;
  }

  /**
   * Get field changes
   */
  public function getFieldChanges(): array
  {
    return $this->fieldChanges;
  }

  /**
   * Get site results
   */
  public function getSiteResults(): array
  {
    return $this->siteResults;
  }

  /**
   * Get the total count of changes
   */
  public function getTotalCount(): int
  {
    return array_sum($this->storageResults);
  }
}