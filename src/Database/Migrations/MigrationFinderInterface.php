<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

/**
 * Interface for finding ACF field migrations
 */
interface MigrationFinderInterface
{
  /**
   * Find all migrations in the directory
   * 
   * @return MigrationInterface[]
   */
  public function findMigrations(): array;

  /**
   * Find a specific migration by name
   * 
   * @param string $name The migration name to find
   * @return MigrationInterface|null
   */
  public function findMigrationByName(string $name): ?MigrationInterface;
}