<?php

namespace Noolan\MyUuid;

class UuidSchema
{
  private $parent;
  protected $table;
  protected $columns;
  protected $indexes;
  protected $triggers;

  public function __construct($parent, $table)
  {
    $this->parent = $parent;
    $this->table = $table;

    $this->columns = [];
    $this->indexes = [];
    $this->triggers = (object) ['added' => [], 'removed' => []];

    return $this;
  }

  public function addColumn($name, $type = null, $length = null, $virtualTarget = null)
  {
    $type = $type ?? (is_null($virtualTarget) ? 'binary' : 'varchar');
    $length = $length ?? (is_null($virtualTarget) ? 16 : 36);

    $this->columns[] = (object) compact('name', 'type', 'length', 'virtualTarget');

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
      $this->indexes[] = (object) compact('column', 'name', 'type', 'length');
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

  public function run()
  {
    $queries = [];

    if (!$this->parent->toBinPreCached) {
      $this->parent->cacheConversionFunctionStrings();
    }

    // add columns
    foreach($this->columns as $column) {
      $sql = "ALTER TABLE `{$this->table}` ADD `{$column->name}` ";
      if (is_null($column->virtualTarget)) {
        $sql .= "binary(16)";
      } else {
        $sql .= "varchar(36) generated always as (" . $this->parent->getToUuidFnString($column->virtualTarget) . ") virtual";
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
      "SET new.`{$trigger}` = {$this->parent->toBinPreCached}UUID(){$this->parent->toBinPostCached};";
    }
    $this->triggers->added = [];

    // remove triggers
    foreach($this->triggers->removed as $trigger) {
      $queries[] = "DROP TRIGGER IF EXISTS `{$this->table}_{$trigger}_auto_uuid`";
    }
    $this->triggers->removed = [];

    $pdo = $this->parent->connection->getPdo();
    $pdo->beginTransaction();
    foreach($queries as $query) {
      $pdo->exec($query);
    }
    $pdo->commit();

    return $queries;
  }
}
