<?php

namespace Noolan\MyUuid;

use Illuminate\Console\Command;

class CheckConfig extends Command
{
  /**
  * The name and signature of the console command.
  *
  * @var string
  */
  protected $signature = 'myuuid:check';

  /**
  * The console command description.
  *
  * @var string
  */
  protected $description = 'Checks if the config matches the database';

  /**
  * Execute the console command.
  *
  * @return mixed
  */
  public function handle()
  {
    $connectionName = config('myuuid.connection', '');
    $myUuid = MyUuid::on($connectionName);

    $this->info("\nMySQL Version Check");
    $this->line("===================\n");

    $version = $myUuid->getVersion();
    $legacy = version_compare($version, '8.0.0', '<');
    $configLegacy = !config('myuuid.mysql_8', true);

    $this->line($version . ' detected');
    $this->line('MySQL 8 mode ' . ($configLegacy ? 'disabled' : 'enabled'));

    if ($legacy === $configLegacy) {
      $this->info('Config is correct!');
    } elseif ($legacy < $configLegacy) {
      $this->question('Config will work but performance will be improved if "mysql_8" is set to true.');
    } else {
      $this->error('Config is not correct. "mysql_8" needs to be false for this connection.');
    }

    $this->info("\nMySQL Trigger Creation");
    $this->line("======================\n");

    $canCreateTriggers = $myUuid->getTrustFunctionCreators();
    $this->line('log_bin_trust_function_creators=' . ($canCreateTriggers ? '1' : '0') . "\n");
    if ($canCreateTriggers) {
      $this->info('Triggers can be created!');
    } else {
      $this->error('Triggers cannot be created.');
      $this->line('log_bin_trust_function_creators is set to 0 (off) which prevents non-deterministic triggers, functions and procedures from being created.');
      $this->line('See https://dev.mysql.com/doc/refman/5.7/en/stored-programs-logging.html for more information.');
    }
    $this->info("\n");

  }
}
