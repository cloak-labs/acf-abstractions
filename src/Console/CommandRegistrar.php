<?php

namespace CloakWP\ACF\Console;

use WP_CLI;
use CloakWP\ACF\Console\Migrations\{MigrateCommand, MakeMigrationCommand, RollbackCommand, RestoreCommand};

class CommandRegistrar
{
  public static function register()
  {
    if (defined('WP_CLI') && WP_CLI) {
      WP_CLI::add_command('acf migrate', MigrateCommand::class);
      WP_CLI::add_command('acf make:migration', MakeMigrationCommand::class);
      WP_CLI::add_command('acf rollback', RollbackCommand::class);
      WP_CLI::add_command('acf restore', RestoreCommand::class);
    }
  }
}