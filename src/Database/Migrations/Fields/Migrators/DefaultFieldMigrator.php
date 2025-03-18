<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Fields\Migrators;

use CloakWP\ACF\Database\Migrations\Fields\FieldMapping;
use CloakWP\ACF\Database\Migrations\Storage\StorageLocationInterface;

class DefaultFieldMigrator extends AbstractFieldMigrator
{
  public function migrateField(
    FieldMapping $fieldMapping,
    StorageLocationInterface $storage,
  ): void {
    $this->migrateFieldData($fieldMapping, $storage);
  }
}