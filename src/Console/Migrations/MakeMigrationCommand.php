<?php

namespace CloakWP\ACF\Console\Migrations;

use WP_CLI;

class MakeMigrationCommand
{
  /**
   * Create a new ACF field migration file
   * 
   * ## OPTIONS
   * 
   * <name>
   * : The name of the migration (e.g. update_company_fields)
   * 
   * [--path=<path>]
   * : Custom path to migrations directory (default: wp-content/acf-migrations)
   * 
   * [--description=<description>]
   * : Optional description for the migration
   */
  public function __invoke($args, $assoc_args)
  {
    $name = $args[0];
    $path = $assoc_args['path'] ?? WP_CONTENT_DIR . '/acf-migrations';
    $description = $assoc_args['description'] ?? 'ACF field migration';

    // Create migrations directory if it doesn't exist
    if (!is_dir($path)) {
      if (!mkdir($path, 0755, true)) {
        WP_CLI::error("Failed to create migrations directory: {$path}");
        return;
      }
    }

    // Generate timestamp
    $timestamp = date('Y_m_d_His');

    // Create filename
    $filename = "{$timestamp}_{$name}.php";
    $filePath = "{$path}/{$filename}";

    // Check if file already exists
    if (file_exists($filePath)) {
      WP_CLI::error("Migration file already exists: {$filePath}");
      return;
    }

    // Get stub content
    $stubContent = $this->getStubContent($name, $description);

    // Write file
    if (file_put_contents($filePath, $stubContent) === false) {
      WP_CLI::error("Failed to write migration file: {$filePath}");
      return;
    }

    // Set basic permissions
    chmod($filePath, 0644);

    WP_CLI::success("Migration file created: {$filePath}");

    // Add helpful next steps
    WP_CLI::line("\nNext steps:");
    WP_CLI::line("1. Follow the comment instructions in the new migration file");
    WP_CLI::line("2. Run the migration with: wp acf migrate");

    // Docker-specific file ownership fix using $USER variable
    WP_CLI::line("\nIf you have trouble saving your migration file edits due to permissions, you can assign yourself the correct ownership by running:");
    WP_CLI::line("  sudo chown \$USER:\$USER {$filePath}");
    WP_CLI::line("Or for all migration files:");
    WP_CLI::line("  sudo chown -R \$USER:\$USER " . dirname($filePath) . "/*.php");
  }

  /**
   * Get the content for the migration stub
   */
  private function getStubContent(string $name, string $description): string
  {
    return <<<PHP
    <?php

    /**
     * Migration: {$name}
     * Description: {$description}
     * Created: {$this->getCurrentDateTime()}
     */

    use CloakWP\ACF\Database\Migrations\Migration;
    use CloakWP\ACF\Database\Migrations\Storage\OptionsStorage;

    return Migration::make('{$name}')
        ->description('{$description}')
        ->before([
          //* copy/paste your old fields here
        ])
        ->after([
          //* copy/paste your new fields here
        ])
        ->contexts([
          //* Tell the Migrator about Field Groups that contain the old fields
          // fn(\$fields) => FieldGroup::make('Settings')->fields(\$fields),
          // fn(\$fields) => FieldGroup::make('Page Fields')->fields(\$fields),
        ])
        ->nameChanges([
          //* Tell the Migrator about non-detectable field name changes (prepend parent field names for best matching, eg. '{parent_name}_{field_name}')
          // 'old_field_name' => 'new_field_name',
        ])
        //* Tell the Migrator where these fields are stored
        ->forStorageLocation(new OptionsStorage())
        // ->forStorageLocation(new PostMetaStorage())
        // ->forStorageLocation(new BlocksStorage())
        ;

    PHP;
  }

  /**
   * Get the current date and time
   */
  private function getCurrentDateTime(): string
  {
    return date('Y-m-d H:i:s');
  }
}