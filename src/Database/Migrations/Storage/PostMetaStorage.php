<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\Storage;

/**
 * Storage location for ACF fields stored in Post Meta
 */
class PostMetaStorage extends AbstractStorageLocation
{
  /**
   * The primary key column name
   * 
   * @var string
   */
  protected string $primaryKeyColumn = 'meta_id';

  /**
   * The parent ID column name (post_id, term_id, user_id, etc.)
   * 
   * @var string
   */
  protected string $parentIdColumn = 'post_id';

  /**
   * The meta key column name
   * 
   * @var string
   */
  protected string $metaKeyColumn = 'meta_key';

  /**
   * The meta value column name
   * 
   * @var string
   */
  protected string $metaValueColumn = 'meta_value';

  /**
   * Get the full table name with prefix
   */
  protected function getTable(): string
  {
    global $wpdb;
    return $wpdb->postmeta;
  }

  /**
   * Read a field value from post meta
   */
  public function read(string $fieldName, bool $isKeyRef = false, ?string $value = null): mixed
  {
    global $wpdb;

    $metaKey = $this->buildFieldName($fieldName, $isKeyRef);

    if ($value) {
      $query = $wpdb->prepare(
        "SELECT {$this->metaValueColumn} FROM {$this->getTable()} WHERE {$this->metaKeyColumn} = %s AND {$this->metaValueColumn} = %s LIMIT 1",
        $metaKey,
        $value
      );
    } else {
      $query = $wpdb->prepare(
        "SELECT {$this->metaValueColumn} FROM {$this->getTable()} WHERE {$this->metaKeyColumn} = %s LIMIT 1",
        $metaKey
      );
    }

    $result = $wpdb->get_var($query);
    return $result !== null ? maybe_unserialize($result) : false;
  }

  /**
   * Update all occurrences of the given old field name with the new field name and value
   */
  public function update(string $oldFieldName, string $newFieldName, mixed $newFieldValue, bool $isKeyRef = false): int
  {
    $oldMetaKey = $this->buildFieldName($oldFieldName, $isKeyRef);

    if ($this->isDryRun()) {
      // For dry runs, estimate the number of rows that would be affected
      global $wpdb;

      // $column = $isKeyRef ? $this->metaValueColumn : $this->metaKeyColumn;

      return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->getTable()} WHERE {$this->metaKeyColumn} = %s",
        $oldMetaKey
      ));
    }

    global $wpdb;

    $newMetaKey = $this->buildFieldName($newFieldName, $isKeyRef);

    // Update existing records
    $numUpdated = $wpdb->update(
      $this->getTable(),
      [
        $this->metaKeyColumn => $newMetaKey,
        $this->metaValueColumn => maybe_serialize($newFieldValue),
      ],
      [$this->metaKeyColumn => $oldMetaKey],
      ['%s', '%s'],
      ['%s']
    );

    return $numUpdated !== false ? $numUpdated : 0;
  }

  /**
   * Delete a field from post meta
   */
  public function delete(string $fieldName, bool $isKeyRef = false): int
  {
    if ($this->isDryRun()) {
      // For dry runs, estimate the number of rows that would be affected
      global $wpdb;
      $metaKey = $this->buildFieldName($fieldName, $isKeyRef);

      return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->getTable()} WHERE {$this->metaKeyColumn} = %s",
        $metaKey
      ));
    }

    global $wpdb;

    $metaKey = $this->buildFieldName($fieldName, $isKeyRef);

    $result = $wpdb->delete(
      $this->getTable(),
      [$this->metaKeyColumn => $metaKey],
      ['%s']
    );

    return $result !== false ? $result : 0;
  }

  /**
   * Find fields matching a pattern
   */
  public function findFields(string $pattern): array
  {
    global $wpdb;

    $query = $wpdb->prepare(
      "SELECT {$this->primaryKeyColumn}, {$this->parentIdColumn}, {$this->metaKeyColumn} 
       FROM {$this->getTable()} 
       WHERE {$this->metaKeyColumn} LIKE %s",
      $pattern
    );

    return $wpdb->get_results($query);
  }

  /**
   * Get the storage type name
   */
  public function getStorageType(): string
  {
    return 'post_meta';
  }
}