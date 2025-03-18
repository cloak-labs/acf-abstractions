<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Migrators;

use CloakWP\ACF\Database\Migrations\Fields\FieldMapping;
use CloakWP\ACF\Database\Migrations\Storage\StorageLocationInterface;

interface FieldMigratorInterface
{
  /**
   * Migrate a field using the provided field mapping
   */
  public function migrateField(
    FieldMapping $fieldMapping,
    StorageLocationInterface $storage,
  ): void;
}