# My UUID

Simplifies using UUIDs in MySQL and Laravel

https://noolan.github.io/my-uuid/

## Install

```bash
composer require noolan/my-uuid
```

## Configure

### Copy Config File

```bash
php artisan vendor:publish --provider Noolan\\MyUuid\\Service
```
The settings for this package can now be edited in `config/myuuid.php`.


### Available Settings

|Key           |Type       |Default              |Description  |
|--------------|-----------|---------------------|-------------|
|`mysql_8`     |Boolean    |`true`               |MySQL 8 adds two new functions, `UUID_TO_BIN` and `BIN_TO_UUID` that simplify working with UUIDs.<br>If `mysql_8` is true, those new functions will be used instead of the mashup of `HEX`, `UNHEX`, and `REPLACE` that is otherwise required.<br>If you are unsure which version of MySQL you have you can run the `php artisan myuuid:version` command detailed below.  |
|`connection`  |String     |`''` (empty string)  |The name of the database connection to use as defined in your `config/database.php` file.<br>An empty string will result in your default connection being used.  |


### Checking Configuration

There are two Artisan commands included with this package that help with configuration; `version` and `check`.

|Command           |Arguments  |Description                                                                        |Example                       |
|------------------|-----------|-----------------------------------------------------------------------------------|------------------------------|
|`myuuid:version`  |(none)     |Outputs the MySQL version of the configured connection.                            |`php artisan myuuid:version`  |
|`myuuid:check`    |(none)     |Checks the current configuration against the database to see if there are issues.  |`php artisan myuuid:check`    |



## Usage

### Instantiation

All the functionality is accessed through the `MyUuid` facade.
```php
use MyUuid;
/* ... */
public function doAThing()
{
    $myUuid = MyUuid::alter('examples');
    /* ... */
}
```

### Chaining

Most methods on the MyUuid class return the object so methods can be chained.

```php
$myUuid->addColumn('uuid', 'blob', 16)
$myUuid->addColumn('parent_id', 'varbinary', 36)
$myUuid->addIndex();

$myUuid->run();

// Can be re-written as:

$myUuid->addColumn('uuid', 'blob', 16)
       ->addColumn('parent_id', 'varbinary', 36)->addIndex()
       ->run();
```

_*Note:* If a non-column function is called without a column name parameter, MyUuid uses the last added column as a default._


### Convenience Functions

There are several functions that make it easy to perform common tasks as long as you don't need to deviate from the default parameters.

```php
/* Add an auto-populating, 16 byte, binary column named 'id'
   and use it as the table's primary key */

$myUuid->addPrimaryUuid('id');

// is equivalent to:

$myUuid->addColumn('id', 'binary', 16)
       ->addIndex('primary')
       ->addTrigger();
```

### Executing Queries and Rolling Back Migrations

Columns and indexes created with MyUuid can be dropped with Laravel's Schema builder. The only thing you have to manually drop is triggers.

```php
// removes auto-population trigger attached to the 'id' column
$myUuid->dropTrigger('id')->run();
```

### API

#### Uuid Methods
<dl>
  <dt><b>alter</b></dt>
  <dd>
    <b>(</b><i>String</i> $table<b>)</b><br>
    <u>returns:</u> new <i>UuidSchema</i>
  </dd>

  <dt><b>getVersion</b></dt>
  <dd>
    ()<br>
    <u>returns:</u> <i>String</i> MySql version
  </dd>

  <dt><b>getTrustFunctionCreatorsSetting</b></dt>
  <dd>
    ()<br>
    <u>returns:</u> <i>Boolean</i> on
  </dd>
</dl>

#### UuidSchema Methods
<dl>

  <dt><b>addColumn</b></dt>
  <dd>
    (<i>String</i> $name, [<i>String</i> $type, <i>Integer</i> $length, <i>String</i> $virtualTarget])<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>addIndex</b></dt>
  <dd>
    ([<i>String</i> $type, <i>String</i> $column, <i>String</i> $cname, <i>Integer</i> $length])<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>addTrigger</b></dt>
  <dd>
    ([<i>String</i> $column])<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>dropTrigger</b></dt>
  <dd>
    (<i>String</i> $column)
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>run</b></dt>
  <dd>
    ()
    <u>returns:</u> null
  </dd>
</dl>

#### UuidSchema Convenience Methods

<dl>

  <dt><b>addPrimaryUuid</b></dt>
  <dd>
    (<i>String</i> $name)<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>addAutoUuid</b></dt>
  <dd>
    (<i>String</i> $name)<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>addFriendlyUuid</b></dt>
  <dd>
    (<i>String</i> $name, <i>String</i> $target)<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>withFriendly</b></dt>
  <dd>
    (<i>String</i> $target)<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>addIndexedUuid</b></dt>
  <dd>
    (<i>String</i> $name)<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>index</b></dt>
  <dd>
    ([<i>String</i> $type])<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>

  <dt><b>addForeignUuid</b></dt>
  <dd>
    (<i>String</i> $name)<br>
    <u>returns:</u> <i>UuidSchema</i> self
  </dd>
</dl>


### Example

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use Noolan\MyUuid\MyUuid;

class CreateExamplesTable extends Migration
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up()
  {
    Schema::create('examples', function (Blueprint $table) {
      $table->timestamps();
      $table->softDeletes();

      $table->string('title')->index();
      $table->text('description');
      $table->longText('code');
    });

    MyUuid::on('examples')
      ->addPrimaryUuid('id')->withFriendly('uuid')
      ->addForeignUuid('category_id')
      ->run();
  }

  /**
  * Reverse the migrations.
  *
  * @return void
  */
  public function down()
  {
    MyUuid::on('examples')->dropTrigger('id')->run();
    Schema::dropIfExists('characters');
  }
}
```
