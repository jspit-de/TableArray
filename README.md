# tableArray 

PHP array manipulation library for arrays with tables structure
Compatible with PHP 5.6+, 7+

### Features

- Create from Array, JSON-String or XML
- Methods for column selection, filtering and sorting

### Usage

#### Simple example

```php
require '/yourpath/tableArray.php';

$data = [ 
  ['name' => 'A1', 'likes' => 3], 
  ['name' => 'A12', 'likes' => 6], 
  ['name' => 'A2','likes' => 14], 
  ['name' => 'A14','likes' => 7], 
];
 
$newData = tableArray::create($data)
  ->select('name AS class, likes') 
  ->orderBy('name ASC NATURAL') 
  ->fetchAll();

$expected = [ 
  ['class' => 'A1', 'likes' => 3], 
  ['class' => 'A2','likes' => 14], 
  ['class' => 'A12', 'likes' => 6], 
  ['class' => 'A14','likes' => 7], 
];
var_dump($newData === $expected); //bool(true)
```
#### Static methods
  * create
  * createFromJson
  * createFromXml
  * check
  
#### Instance methods
  * select
  * filter
  * filterLikeAll
  * filterLikeIn
  * orderBy
  * offset
  * limit
  * innerJoin
  * leftJoin
  * pivot
  * flatten
  * addFlatKeys
  * addSqlFunction
  * firstRowToKey
  * fetchAll
  * fetchKeyValue
  * fetchAllObj
  * fetchRaw

### Demo and Test

http://jspit.de/check/phpcheck.class.tablearray.php

### Requirements

- PHP 5.6+
