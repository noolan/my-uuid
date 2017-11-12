<?php

namespace Noolan\MyUuid;

trait Identifyable {

  public $increments = false;
  protected $keyType = 'string';

  // convert string UUID to ordered time byte representation
  public function __call($method, $parameters)
  {
    if ($method == 'find') {
      $parameters[0] = MyUuid::uuidToBin($parameters[0]);
    }

    return parent::__call($method, $parameters);
  }

}
