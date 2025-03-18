<?php

namespace CloakWP\ACF\Console\Migrations;

use WP_CLI;

class RestoreCommand
{
  /**
   * Restore database from a backup
   * 
   * ## OPTIONS
   * 
   * [<file>]
   * : The backup file to restore from. If not provided, shows a list of available backups.
   * 
   * [--backup-dir=<path>]
   * : Directory where backups are stored (default: wp-content/backups)
   * 
   * [--list]
   * : List available backups
   * 
   * [--force]
   * : Skip confirmation prompt
   * 
   * [--dry-run]
   * : Show what would be restored without actually restoring
   */
  public function __invoke($args, $assoc_args)
  {
    $backupDir = $assoc_args['backup-dir'] ?? WP_CONTENT_DIR . '/backups';
    $listOnly = isset($assoc_args['list']);
    $force = isset($assoc_args['force']);
    $dryRun = isset($assoc_args['dry-run']);

    if ($dryRun) {
      WP_CLI::line("Running in dry run mode - no database changes will be made");
    }

    // Check if backup directory exists
    if (!is_dir($backupDir)) {
      WP_CLI::error("Backup directory not found: {$backupDir}");
      return;
    }

    // Get list of backup files
    $backupFiles = glob($backupDir . '/acf-migration-backup-*.sql');

    if (empty($backupFiles)) {
      WP_CLI::error("No backup files found in {$backupDir}");
      return;
    }

    // Sort by filename (newest first)
    rsort($backupFiles);

    // If list option is set, just show available backups
    if ($listOnly) {
      WP_CLI::line("Available backups:");
      foreach ($backupFiles as $index => $file) {
        $filename = basename($file);
        $size = size_format(filesize($file));
        $date = date('Y-m-d H:i:s', filemtime($file));
        WP_CLI::line(sprintf("%d. %s (%s) - %s", $index + 1, $filename, $size, $date));
      }
      return;
    }

    // If no file is provided, show list and ask user to choose
    if (empty($args)) {
      WP_CLI::line("Available backups:");
      foreach ($backupFiles as $index => $file) {
        $filename = basename($file);
        $size = size_format(filesize($file));
        $date = date('Y-m-d H:i:s', filemtime($file));
        WP_CLI::line(sprintf("%d. %s (%s) - %s", $index + 1, $filename, $size, $date));
      }

      $choice = \cli\prompt("Enter the number of the backup to restore", null, STDIN);
      $choice = (int) $choice - 1;

      if (!isset($backupFiles[$choice])) {
        WP_CLI::error("Invalid selection");
        return;
      }

      $backupFile = $backupFiles[$choice];
    } else {
      $backupFile = $args[0];

      // If only filename was provided, prepend the backup directory
      if (!file_exists($backupFile) && file_exists($backupDir . '/' . $backupFile)) {
        $backupFile = $backupDir . '/' . $backupFile;
      }

      if (!file_exists($backupFile)) {
        WP_CLI::error("Backup file not found: {$backupFile}");
        return;
      }
    }

    if ($dryRun) {
      WP_CLI::success("Would restore database from " . basename($backupFile));
      return;
    }

    if (!$force) {
      WP_CLI::confirm("Are you sure you want to restore the database from " . basename($backupFile) . "? This will overwrite your current database.");
    }

    WP_CLI::line("Restoring database from backup...");

    // Use WP-CLI to import the database
    $command = "db import {$backupFile}";
    $result = WP_CLI::runcommand($command, [
      'return' => true,
      'exit_error' => false,
    ]);

    if ($result !== false) {
      WP_CLI::success("✓ Database restored successfully from " . basename($backupFile));
    } else {
      WP_CLI::error("✗ Failed to restore database from " . basename($backupFile));
    }
  }
}