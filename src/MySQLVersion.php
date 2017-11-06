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
  protected $signature = 'myuuid:version {connection? : The name of connection to check}';

  /**
  * The console command description.
  *
  * @var string
  */
  protected $description = 'Get the MySQL version of a connection.';

  /**
  * Execute the console command.
  *
  * @return mixed
  */
  public function handle()
  {
    $connectionName = $this->argument('connection') ?? config('myuuid.connection', '');

    $this->info(MyUuid::on('', $connectionName)->getVersion());
  }
}
