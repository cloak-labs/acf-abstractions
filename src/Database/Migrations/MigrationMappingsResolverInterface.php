<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\{MigrationInterface, ResolvedMigration};
use CloakWP\ACF\Database\Migrations\Fields\Resolvers\FieldResolverInterface;

/**
 * Interface for MigrationMappingsResolvers, which resolve the user-provided ACF field structures 
 * and generate the mappings of field keys to be migrated.
 */
interface MigrationMappingsResolverInterface
{
  /**
   * Receives a Migration and returns a ResolvedMigration, which contains the necessary data to perform the migration.
   */
  public function resolve(MigrationInterface $migration): ResolvedMigration;

  /**
   * Register a field type resolver
   */
  public function registerTypeResolver(FieldResolverInterface $resolver): static;

  /**
   * Register a field collection resolver
   */
  public function registerCollectionResolver(FieldResolverInterface $resolver): static;
}