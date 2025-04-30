<?php

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\Storage\StorageLocationInterface;
use CloakWP\ACF\FieldGroup;

abstract class AbstractMigration implements MigrationInterface
{
  /**
   * Migration name
   */
  protected string $name = '';

  /**
   * Migration description
   */
  protected string $description = '';

  /**
   * Old field structure (original)
   */
  protected $oldFields = null;

  /**
   * New field structure (original)
   */
  protected $newFields = null;

  /**
   * Name changes for fields
   */
  protected array $nameChanges = [];

  /**
   * Storage locations
   * @param StorageLocationInterface[]
   */
  protected array $storageLocations = [];

  /**
   * Field value transformers indexed by field name
   * 
   * @var array<string, callable>
   */
  protected array $fieldValueTransformers = [];


  /**
   * Constructor
   */
  public function __construct(string $name = '')
  {
    $this->name = $name;
  }

  /**
   * Static constructor
   */
  public static function make(string $name = ''): static
  {
    return new static($name);
  }

  public function description(string $description): static
  {
    $this->description = $description;
    return $this;
  }

  public function before(mixed $fields): static
  {
    $this->oldFields = $fields;
    return $this;
  }

  public function after(mixed $fields): static
  {
    $this->newFields = $fields;
    return $this;
  }

  public function nameChanges(array $changes): static
  {
    $this->nameChanges = $changes;
    return $this;
  }

  public function forStorageLocation(StorageLocationInterface $location): static
  {
    $this->storageLocations[] = $location;
    return $this;
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


  // Getters
  public function getName(): string
  {
    return $this->name;
  }
  public function getDescription(): string
  {
    return $this->description;
  }
  public function getOldFields(): FieldGroup|array
  {
    return $this->oldFields;
  }
  public function getNewFields(): FieldGroup|array
  {
    return $this->newFields;
  }
  public function getNameChanges(): array
  {
    return $this->nameChanges;
  }
  public function getStorageLocations(): array
  {
    return $this->storageLocations;
  }
}
