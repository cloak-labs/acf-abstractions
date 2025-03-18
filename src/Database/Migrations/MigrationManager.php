<?php

declare(strict_types=1);

namespace CloakWP\ACF\Database\Migrations;

use CloakWP\ACF\Database\Migrations\Migration;
use CloakWP\ACF\Database\Migrations\Migrator;
use CloakWP\ACF\Database\Migrations\Traits\RegistersFieldMigrators;
use CloakWP\ACF\Database\Migrations\Storage\{
    PostMetaStorage,
    OptionsStorage,
    BlocksStorage,
    TermMetaStorageLocation,
    UserMetaStorageLocation
};

/**
 * Singleton manager for ACF migrations
 * 
 * This class stores global configuration that persists across WP-CLI command invocations.
 * Users can configure it in their plugin or theme's functions.php file.
 */
class MigrationManager
{
    use RegistersFieldMigrators;

    /**
     * Singleton instance
     */
    private static ?self $instance = null;

    /**
     * Default storage locations to use for migrations
     */
    private array $defaultStorageLocations = [];

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        // Register default storage locations
        $this->defaultStorageLocations = [
            'post' => new PostMetaStorage(),
            'options' => new OptionsStorage(),
            'block' => new BlocksStorage(),
            // 'term' => new TermMetaStorageLocation(),
            // 'user' => new UserMetaStorageLocation()
        ];
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a custom storage location
     */
    public function registerStorageLocation(string $name, StorageLocationInterface $location): self
    {
        $this->defaultStorageLocations[$name] = $location;
        return $this;
    }

    /**
     * Create a configured migrator with all registered migrators
     */
    public function createMigrator(bool $isDryRun = false): Migrator
    {
        $migrator = new Migrator();
        $migrator->setDryRun($isDryRun);

        // Register all custom field migrators
        foreach ($this->fieldMigrators as $type => $migrator) {
            $migrator->registerFieldMigrator($type, $migrator);
        }

        return $migrator;
    }

    /**
     * Apply default storage locations to a migration
     */
    public function applyDefaultLocations(Migration $migration): Migration
    {
        foreach ($this->defaultStorageLocations as $location) {
            $migration->registerStorageLocation($location);
        }

        return $migration;
    }

    /**
     * Run a migration with all configured migrators and locations
     */
    public function run(Migration $migration, bool $isDryRun = false): array
    {
        $migrator = $this->createMigrator($isDryRun);
        return $migrator->run($migration);
    }

    /**
     * Helper methods for creating storage locations
     */
    public static function postsLocation(): PostMetaStorage
    {
        return new PostMetaStorage();
    }

    public static function optionsLocation(string $optionName = 'options'): OptionsStorage
    {
        return new OptionsStorage($optionName);
    }

    public static function blocksLocation(): BlocksStorage
    {
        return new BlocksStorage();
    }

    // public static function termsLocation(): TermMetaStorageLocation
    // {
    //     return new TermMetaStorageLocation();
    // }

    // public static function usersLocation(): UserMetaStorageLocation
    // {
    //     return new UserMetaStorageLocation();
    // }
}