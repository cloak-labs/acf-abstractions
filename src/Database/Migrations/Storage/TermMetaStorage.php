<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Storage;

/**
 * Storage location for ACF fields stored in Term Meta
 */
class TermMetaStorage extends PostMetaStorage
{
  protected string $parentIdColumn = 'term_id';

  protected function getTable(): string
  {
    global $wpdb;
    return $wpdb->termmeta;
  }

  public function getStorageType(): string
  {
    return 'term_meta';
  }
}