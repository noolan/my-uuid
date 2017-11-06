<?php
use Illuminate\Support\Facades\Facade;
class MyUuid extends Facade
{
  protected static function getFacadeAccessor() { return 'myuuid'; }
}
