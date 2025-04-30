<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\Storage\StorageLocationInterface;

/**
 * Represents an ACF field migration
 */
class Migration extends AbstractMigration
{
  /**
   * Generate a rollback migration that reverses the changes made by this migration
   */
  public function generateRollback(): RollbackMigration
  {
    return RollbackMigration::from($this);
  }
}
