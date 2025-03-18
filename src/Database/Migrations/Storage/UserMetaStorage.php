<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Storage;

class UserMetaStorage extends PostMetaStorage
{
  protected string $primaryKeyColumn = 'umeta_id';
  protected string $parentIdColumn = 'user_id';

  protected function getTable(): string
  {
    global $wpdb;
    return $wpdb->usermeta;
  }

  public function getStorageType(): string
  {
    return 'user_meta';
  }
}