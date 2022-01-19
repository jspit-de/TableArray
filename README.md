# TableArray 

PHP library for arrays with tableslike structure (V2.6.1)

### Features

- Create from Array, JSON-String, CSV-String, Iterator or XML
- Methods for column selection, row filtering and sorting

### Usage

#### Simple example 1

```php
use Jspit\TableArray;
require '/yourpath/TableArray.php';

$data = [ 
  ['id' => 1, 'val' => 23.333333333], 
  ['id' => 2, 'val' => 13.7777777777], 
]; 
$newData = TableArray::create($data) 
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
use Jspit\TableArray;
require '/yourpath/TableArray.php';

$data = [ 
  ['name' => 'A1', 'likes' => 3], 
  ['name' => 'A12', 'likes' => 6], 
  ['name' => 'A2','likes' => 14], 
  ['name' => 'A14','likes' => 7], 
];
 
$newData = TableArray::create($data)
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

#### Pivot Group example

```php
use Jspit\TableArray;
require '/yourpath/TableArray.php';

$data = [ 
  ['group' => 1, 'type' => 'A', 'value' => 'AA'],
  ['group' => 2, 'type' => 'A', 'value' => 'BB'], 
  ['group' => 1, 'type' => 'B', 'value' => 5],
  ['group' => 2, 'type' => 'B', 'value' => 7], 
]; 
$newData = TableArray::create($data) 
  ->pivot('group','value','type')
  ->fetchAll();

$expected = [
  1 => ['group' => 1, 'value.A' => "AA", 'value.B' => 5 ],
  2 => ['group' => 2, 'value.A' => "BB", 'value.B' => 7 ],
];

```

#### CSV Import example 

```php
use Jspit\TableArray;
require '/yourpath/TableArray.php';

$csv = new SplFileObject('datei.csv');

$csv->setFlags(SplFileObject::READ_CSV 
  | SplFileObject::SKIP_EMPTY 
  | SplFileObject::READ_AHEAD 
  | SplFileObject::DROP_NEW_LINE
);

$tabArr = TableArray::create($csv)
  //Using first row of CSV as the array keys
  ->firstRowToKey()  
  ->fetchAll()
;
```

#### Example filterGroupAggregate 
 
```php
use Jspit\TableArray;
require '/yourpath/TableArray.php';

$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2, 'value2' => 3], 
  ['id' => "2",'group' => 2, 'value' => 4, 'value2' => 7],
  ['id' => "3",'group' => 1, 'value' => 1, 'value2' => 2], 
  ['id' => "4",'group' => 2, 'value' => 6, 'value2' => 8],
];

$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'MAX', 'value2' => 'AVG'],['group'])
  ->orderBy('value2 DESC')
  ->fetchAll();

$expected = [ 
  ['id' => "4",'group' => 2, 'value' => 6, 'value2' => 7.5],
  ['id' => "1",'group' => 1, 'value' => 2, 'value2' => 2.5], 
];
var_dump($newData === $expected);  //bool(true) 
```

#### Data input methods
  * new TableArray ($dataArray,[$keyPathToData])
  * create ($dataArray,[$keyPathToData])
  * createFromJson ($jsonStr,[$keyPathToData])
  * createFromXml ($xml, [$strXPath])
  * createFromOneDimArray ($dataArray,[$delimiter])
  * createFromString ($inputString, [$regExValues,[$regExSplitLines]])
  * createFromGroupedArray($input, $keyArray)
  * createFromCsvFile([$file])
  
#### General Working methods
  * [select](#select)
  * [orderBy](#orderBy)
  * innerJoinOn
  * leftJoinOn
  * flatten
  * merge
  * pivot
  * offset
  * limit
  * transpose
  * collectChilds

#### Filter methods
  * filter
  * filterEqual
  * filterLikeAll
  * filterLikeIn
  * filterUnique
  * filterGroupAggregate

#### Methods to fetch the data
  * fetchAll
  * fetchAllObj
  * fetchAllAsJson
  * fetchAllAsCSV
  * fetchKeyValue
  * fetchColumn
  * fetchColumnUnique
  * fetchGroup
  * fetchRow
  * fetchRaw
  * fetchLimit
  * fetchLimitFromEnd

#### Other methods
  * addFlatKeys
  * addKeys
  * fieldAsKey
  * firstRowToKey
  * addSqlFunction
  * addSqlFunctionFromArray
  * getSqlFunction
  * fieldNameRaw
  * setOption
  * getOption
  * setCsvDefaultOptions($options)
  * check($data)
  * unGroup($array, $keys)
  * count
  * toClass
  * print($comment,$limit)
  
#### Internal functions may be used by select and orderBy
  * ABS(fieldName)
  * UPPER(fieldName)
  * FIRSTUPPER(fieldName)
  * LOWER(fieldName)
  * TRIM(fieldName[,'character_mask'])
  * FORMAT('format',fieldName[,fieldName])
  * SCALE(fieldName,'factor'[,'add'[,'format']])
  * DATEFORMAT('dateFormat',fieldName)
  * REPLACE('search','replace',fieldName)
  * SUBSTR(fieldName,'start'[,'length'])
  * LIKE(fieldName,'likePattern')
  * INTVAL(fieldName,'basis')
  * FLOATVAL(fieldName,['dec_point', 'thousands_sep'])
  * NULLCOUNT(fieldName[,fieldName,..])
  * CONCAT(fieldName[,fieldName,..])
  * IMPLODE(arrayFieldName,['delimiter'])
  * SPLIT(fieldName[,'delimiter'[,'number']])
  
#### Interface
  * Iterator 
  * JsonSerializable
  
#### Class Methods

##### select

Select rows for a fetch
  ->select('field1, field2,..') 
  ->select('field1 as newName,..')
  ->select('fct(field1) as newName,..)

```php
$data =[
  ['id' => 1, 'article' => "pc1", 'price' => 1231.0],
  ['id' => 1, 'article' => "pc2", 'price' => 471.5],
];

$newData = TableArray::create($data)
  ->select("article as Name, FORMAT('%.2f€',price) as Euro")
  ->fetchAll()
;
/* Result $newData
[
  ['Name' => "pc1", 'Euro' => "1231.00€"],
  ['Name' => "pc2", 'Euro' => "471.50€",
]
*/
```

##### orderBy

Sorts the array by one or more columns in ascending or descending order.

  ->orderBy('field1 [ASC|DESC][NATURAL], [field2..]') 

  ->orderBy('fct(field1,[params]),[field|function..])

### Documentation

http://jspit.de/tools/classdoc.php?class=TableArray
 
### Examples and Test

http://jspit.de/check/phpcheck.class.tablearray.php

### Requirements

- PHP 7.x
