<?php

namespace Noolan\MyUuid;

use Illuminate\Support\Facades\Facade;

class MyUuid extends Facade
{
  protected static function getFacadeAccessor()
  {
    return 'MyUuid';
  }
}
