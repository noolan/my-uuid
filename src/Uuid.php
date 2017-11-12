<?php

namespace Noolan\MyUuid;

use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\{Codec, UuidFactory};

class Uuid
{
  private static $toBin = "UUID_TO_BIN(?, 1)";
  private static $toBinLegacy = "UNHEX(UPPER(REPLACE(?, '-', '')))";
  private static $toUuid = "BIN_TO_UUID(?, 1)";
  private static $toUuidLegacy = "INSERT(INSERT(INSERT(INSERT(HEX(?), 20, 0, '-'), 16, 0, '-'), 12, 0, '-'), 8, 0, '-')";

  public $legacy;
  public $connection;

  public $toBinPreCached;
  public $toBinPostCached;
  public $toUuidPreCached;
  public $toUuidPostCached;

  private $uuidFactory;

  public function __construct($connection = null)
  {
    $this->legacy = !config('myuuid.mysql_8', true);

    if (is_null($connection)) {
      $connection = config('myuuid.connection', '');
    }
    if (is_string($connection)) {
      $connection = $connection === '' ? DB::connection($connection) : DB::connection();
    }
    $this->connection = $connection;

    return $this;
  }

  public function alter($table)
  {
    return new UuidSchema($this, $table);
  }

  public function getVersion()
  {
    $pdo = $this->connection->getPdo();
    $result = $pdo->query('SELECT version()')->fetchColumn();

    preg_match("/^[0-9\.]+/", $result, $version);

    return $version[0];
  }

  public function getTrustFunctionCreatorsSetting()
  {
    $pdo = $this->connection->getPdo();
    $result = $pdo->query('SELECT @@log_bin_trust_function_creators')->fetchColumn();

    return (boolean) $result;
  }


  public function cacheConversionFunctionStrings()
  {
    $legacy = ($this->legacy ? 'Legacy' : '');

    // toBinFunction
    $toBinFn = explode('?', static::${'toBin' . $legacy});
    $this->toBinPreCached = $toBinFn[0];
    $this->toBinPostCached = $toBinFn[1];

    // toUuidFunction
    $toUuidFn = explode('?', static::${'toUuid' . $legacy});
    $this->toUuidPreCached = $toUuidFn[0];
    $this->toUuidPostCached = $toUuidFn[1];
  }

  public function getToBinFnString($column)
  {
    if (!$this->toBinPreCached) {
      $this->cacheConversionFunctionStrings();
    }
    return $this->toBinPreCached . $column . $this->toBinPostCached;
  }

  public function getToUuidFnString($column)
  {
    if (!$this->toUuidPreCached) {
      $this->cacheConversionFunctionStrings();
    }
    return $this->toUuidPreCached . $column . $this->toUuidPostCached;
  }

  private function makeUuidFactory()
  {
    if (is_null($this->uuidFactory)) {
      $this->uuidFactory = new UuidFactory();

      $orderedTime = new Codec\OrderedTimeCodec(
        $this->uuidFactory->getUuidBuilder()
      );

      $this->uuidFactory->setCodec($orderedTime);
    }
  }

  public function uuidToBin($uuid)
  {
    $this->makeUuidFactory();

    return $this->uuidFactory->fromString($uuid)->getBytes();
  }

  public function binToUuid($bin)
  {
    $this->makeUuidFactory();

    return $this->uuidFactory->fromBytes($uuid)->getString();
  }

}
