# tableArray 

PHP array manipulation library for arrays with tables structure
Compatible with PHP 5.6+, 7+

### Features

- Create from Array, JSON-String or XML
- Methods for column selection, row filtering and sorting

### Usage

#### Simple example 1

```php
require '/yourpath/tableArray.php';

$data = [ 
  ['id' => 1, 'val' => 23.333333333], 
  ['id' => 2, 'val' => 13.7777777777], 
]; 
$newData = tableArray::create($data) 
  ->select('id, FORMAT("%6.2f",val) as rval') 
  ->orderBy('val ASC')
  ->fetchAll(); 
  
$expected = [ 
  ['id' => 2, 'rval' => " 13.78"],
  ['id' => 1, 'rval' => " 23.33"],  
]; 
var_dump($newData == $expected); //bool(true)
```

#### Simple example 2

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
  * innerJoinOn
  * leftJoinOn
  * pivot
  * flatten
  * addFlatKeys
  * addSqlFunction
  * getSqlFunction
  * firstRowToKey
  * fetchAll
  * fetchKeyValue
  * fetchAllObj
  * fetchRaw
  
### Internal functions may be used by select and orderBy
  * UPPER
  * LOWER
  * FORMAT
  * DATEFORMAT
  * REPLACE
  * SUBSTR
  * LIKE
  * INTVAL
  * FLOATVAL
  
### Demo and Test

http://jspit.de/check/phpcheck.class.tablearray.php

### Requirements

- PHP 5.6+, PHP 7.x
