# tableArray 

PHP library for arrays with tableslike structure

### Features

- Create from Array, JSON-String, Iterator or XML
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

#### CSV Import example 

```php
require '/yourpath/tableArray.php';

$csv = new SplFileObject('datei.csv');

$csv->setFlags(SplFileObject::READ_CSV 
  | SplFileObject::SKIP_EMPTY 
  | SplFileObject::READ_AHEAD 
  | SplFileObject::DROP_NEW_LINE
);

$tabArr = tableArray::create($csv)
  //Using first row of CSV as the array keys
  ->firstRowToKey()  
  ->fetchAll()
;
```


#### Static methods
  * create
  * createFromJson
  * createFromXml
  * createFromOneDimArray
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
  * addSqlFunctionFromArray
  * getSqlFunction
  * firstRowToKey
  * fetchAll
  * fetchAllObj
  * fetchKeyValue
  * fetchColumn
  * fetchColumnUnique
  * fetchGroup
  * fetchRaw
  * fetchLimit
  
### Internal functions may be used by select and orderBy
  * UPPER(fieldName)
  * LOWER(fieldName)
  * TRIM(fieldName[,'character_mask'])
  * FORMAT('format',fieldName[,fieldName])
  * SCALE(fieldName,'factor'[,add[,format]])
  * DATEFORMAT('dateFormat',fieldName)
  * REPLACE('search','replace',fieldName)
  * SUBSTR(fieldName,'start'[,length])
  * LIKE(fieldName,'likePattern')
  * INTVAL(fieldName)
  * FLOATVAL(fieldName)
  
### Examples and Test

http://jspit.de/check/phpcheck.class.tablearray.php

### Requirements

- PHP 5.6+, PHP 7.x
