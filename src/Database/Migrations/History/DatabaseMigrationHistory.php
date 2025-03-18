<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations\History;

use CloakWP\ACF\Database\Migrations\MigrationInterface;

/**
 * Tracks migration history in the database
 */
class DatabaseMigrationHistory implements MigrationHistoryInterface
{
  /**
   * Table name for migration history
   */
  private string $tableName;

  /**
   * Constructor
   */
  public function __construct(string $tableName = 'acf_migrations')
  {
    $this->tableName = $tableName;
    $this->ensureTableExists();
  }

  /**
   * Ensure the migration history table exists
   */
  private function ensureTableExists(): void
  {
    global $wpdb;

    $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tableName}'") === $this->tableName;

    if (!$tableExists) {
      $charset_collate = $wpdb->get_charset_collate();

      $sql = "CREATE TABLE {$this->tableName} (
          id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
          name varchar(255) NOT NULL,
          description text,
          batch int(11) NOT NULL,
          run_at datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY  (id),
          UNIQUE KEY name (name)
      ) $charset_collate;";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);
    }
  }

  /**
   * Get the next batch number
   */
  private function getNextBatchNumber(): int
  {
    global $wpdb;

    $batch = $wpdb->get_var("SELECT MAX(batch) FROM {$this->tableName}");
    return (int) $batch + 1;
  }

  /**
   * Check if a migration has been run
   */
  public function hasRun(string $migrationName): bool
  {
    global $wpdb;

    $count = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->tableName} WHERE name = %s",
        $migrationName
      )
    );

    return (int) $count > 0;
  }

  /**
   * Add a migration to history
   */
  public function add(MigrationInterface $migration): bool
  {
    global $wpdb;

    $batch = $this->getNextBatchNumber();

    $result = $wpdb->insert(
      $this->tableName,
      [
        'name' => $migration->getName(),
        'description' => $migration->getDescription(),
        'batch' => $batch
      ]
    );

    return $result !== false;
  }

  /**
   * Remove a migration from history
   */
  public function remove(string $migrationName): bool
  {
    global $wpdb;

    $result = $wpdb->delete(
      $this->tableName,
      ['name' => $migrationName]
    );

    return $result !== false;
  }

  /**
   * Get all migrations that have been run
   */
  public function getAll(): array
  {
    global $wpdb;

    $migrations = $wpdb->get_results(
      "SELECT * FROM {$this->tableName} ORDER BY id ASC",
      ARRAY_A
    );

    return $migrations ?: [];
  }

  /**
   * Get the most recent migrations
   */
  public function getRecent(int $limit = 10): array
  {
    global $wpdb;

    $migrations = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$this->tableName} ORDER BY id DESC LIMIT %d",
        $limit
      ),
      ARRAY_A
    );

    return $migrations ?: [];
  }

  /**
   * Get migrations from a specific batch
   */
  public function getBatch(int $batch): array
  {
    global $wpdb;

    $migrations = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$this->tableName} WHERE batch = %d ORDER BY id ASC",
        $batch
      ),
      ARRAY_A
    );

    return $migrations ?: [];
  }

  /**
   * Get the last batch number
   */
  public function getLastBatchNumber(): int
  {
    global $wpdb;

    $batch = $wpdb->get_var("SELECT MAX(batch) FROM {$this->tableName}");
    return (int) $batch;
  }

  /**
   * Remove all migrations from a specific batch
   */
  public function removeBatch(int $batch): bool
  {
    global $wpdb;

    $result = $wpdb->delete(
      $this->tableName,
      ['batch' => $batch]
    );

    return $result !== false;
  }
}