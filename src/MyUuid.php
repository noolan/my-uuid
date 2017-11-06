<?php

namespace Noolan\MyUuid;

use Illuminate\Support\Facades\DB;

class MyUuid
{
  private static $toBin = "UUID_TO_BIN(?, 1)";
  private static $toBinLegacy = "UNHEX(UPPER(REPLACE(?, '-', '')))";
  private static $toUuid = "BIN_TO_UUID(?, 1)";
  private static $toUuidLegacy = "INSERT(INSERT(INSERT(INSERT(HEX(?), 20, 0, '-'), 16, 0, '-'), 12, 0, '-'), 8, 0, '-')";

  protected $legacy;
  protected $table;
  protected $connection;
  protected $columns;
  protected $indexes;
  protected $triggers;

  private $toBinPreCached;
  private $toBinPostCached;
  private $toUuidPreCached;
  private $toUuidPostCached;

  public static function on($table, $connection = null)
  {
    return new self($table, $connection);
  }

  public function __construct($table, $connection = null)
  {
    $this->legacy = !config('myuuid.mysql_8', true);

    $this->table = $table;

    if (is_null($connection)) {
      $connection = config('myuuid.connection', '');
    }
    if (is_string($connection)) {
      $connection = $connection === '' ? DB::connection($connection) : DB::connection();
    }
    $this->connection = $connection;

    $this->columns = [];
    $this->indexes = [];
    $this->triggers = (object) ['added' => [], 'removed' => []];

    return $this;
  }

  public function addColumn($name, $type = null, $length = null, $virtualTarget = null)
  {
    $type = $type ?? (is_null($virtualTarget) ? 'binary' : 'varchar');
    $length = $length ?? (is_null($virtualTarget) ? 16 : 36);

    $this->columns[] = (object) compact($name, $type, $length, $virtualTarget);
    return $this;
  }

  public function addIndex($type = 'index', $column = null, $name = null, $length = null)
  {
    $currentColumn = end($this->columns);
    $column = $column ?? $currentColumn->name;
    $name = $name ?? "{$this->table}_{$column}_{$type}" . ($type !== 'index' ? '_index' : '');
    $length = $length ?? $currentColumn->length ?? 16;

    // no indexes on virtual columns
    if ($column !== $currentColumn->name || is_null($currentColumn->virtualTarget)) {
      $this->indexes[] = (object) compact($column, $name, $type, $length);
    }

    return $this;
  }

  public function index($type = 'index')
  {
    return $this
      ->addIndex($type);
  }

  public function addTrigger($column = null)
  {
    $currentColumn = end($this->columns);
    $column = $column ?? $currentColumn->name;

    $this->triggers->added[] = $column;

    return $this;
  }

  public function dropTrigger($column)
  {
    $this->triggers->removed[] = $column;

    return $this;
  }

  /* Convenience Functions */
  public function addPrimaryUuid($column)
  {
    return $this
      ->addColumn($column)
      ->addIndex('primary')
      ->addTrigger();
  }

  public function addAutoUuid($column)
  {
    return $this
      ->addColumn($column)
      ->addTrigger();
  }

  public function addFriendlyUuid($column, $target)
  {
    return $this
      ->addColumn($column, 'varchar', 36, $target);
  }

  public function withFriendly($column)
  {
    $currentColumn = end($this->columns);
    $target = $currentColumn->name;

    return $this
      ->addFriendlyUuid($column, $target);
  }

  public function addIndexedUuid($column)
  {
    return $this
      ->addColumn($column)
      ->addIndex();
  }

  public function addForeignUuid($column)
  {
    return $this
      ->addIndexedUuid($column);
  }


  /*                       */

  private function cacheConversionFunctionStrings()
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

  public function run()
  {
    $queries = [];

    // add columns
    foreach($this->columns as $column) {
      $sql = "ALTER TABLE `{$this->table}` ADD `{$column->name}` ";
      if (is_null($column->virtualTarget)) {
        $sql .= "binary(16)";
      } else {
        $sql .= "varchar(36) generated always as (" . $this->getToUuidFnString($column->virtualTarget) . ") virtual";
      }
      $queries[] = $sql;
    }
    $this->columns = [];

    // add indexes
    foreach($this->indexes as $index) {
      if ($index->type === 'primary') {
        $queries[] = "ALTER TABLE `{$this->table}` ADD PRIMARY KEY(`{$index->column}`)";
      } else {
        $queries[] = "CREATE " .
        ($index->type !== 'index' ? strtoupper($index->type) . ' ' : '') .
        "INDEX `{$index->name}` " .
        "ON `{$this->table}` (`{$index->column}`({$index->length}))";
      }
    }
    $this->indexes = [];

    // add triggers
    foreach($this->triggers->added as $trigger) {
      $queries[] = "CREATE TRIGGER `{$this->table}_{$trigger}_auto_uuid` " .
      "BEFORE INSERT ON `{$this->table}` FOR EACH ROW " .
      "SET new.`{$trigger}` = {$toBinPre}UUID(){$toBinPost};";
    }
    $this->triggers->added = [];

    // remove triggers
    foreach($this->triggers->removed as $trigger) {
      $queries[] = "DROP TRIGGER IF EXISTS `{$this->table}_{$trigger}_auto_uuid`";
    }
    $this->triggers->removed = [];

    $pdo = $this->connection->getPdo();
    $pdo->beginTransaction();
    foreach($queries as $query) {
      $pdo->exec($query);
    }
    $pdo->commit();

    return $queries;
  }


  function getVersion() {
    $pdo = $this->connection->getPdo();
    $result = $pdo->query('SELECT version()')->fetchColumn();

    preg_match("/^[0-9\.]+/", $result, $version);

    return $version[0];
  }

}
