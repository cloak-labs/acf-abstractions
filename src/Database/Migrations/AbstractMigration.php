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