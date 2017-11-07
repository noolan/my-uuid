<?php

namespace Noolan\MyUuid;

use Illuminate\Support\Facades\Facade;

class MyUuidFacade extends Facade
{
  protected static function getFacadeAccessor() { return 'myuuid'; }
}
