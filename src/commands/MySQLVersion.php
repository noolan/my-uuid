<?php

namespace Noolan\MyUuid;

use Illuminate\Console\Command;

class MySQLVersion extends Command
{
  /**
  * The name and signature of the console command.
  *
  * @var string
  */
  protected $signature = 'myuuid:version';

  /**
  * The console command description.
  *
  * @var string
  */
  protected $description = 'Gets the MySQL version of the configured connection';

  /**
  * Execute the console command.
  *
  * @return mixed
  */
  public function handle()
  {
    $this->info(MyUuid::getVersion());
  }
}
