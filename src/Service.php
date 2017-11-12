<?php

namespace Noolan\MyUuid;

use Illuminate\Support\ServiceProvider;

class Service extends ServiceProvider
{
  /**
  * Bootstrap the application services.
  *
  * @return void
  */
  public function boot()
  {
    if ($this->app->runningInConsole()) {
      $this->commands([
        CheckConfig::class,
        MySQLVersion::class,
      ]);
    }

    $this->publishes([
      __DIR__.'/config.php' => config_path('myuuid.php')
    ]);
  }

  /**
  * Register the application services.
  *
  * @return void
  */
  public function register()
  {
    $this->app->singleton('MyUuid', function ($app) {
      return new Uuid();
    });

    $this->app->singleton();
  }
}
