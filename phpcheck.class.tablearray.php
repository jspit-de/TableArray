<?php
//2022-01-11
//check for TableArray V2.6
////Comment out the following line to use the class without a namespace
use Jspit\TableArray;

error_reporting(-1);
//error_reporting(E_ALL ^ (E_WARNING | E_USER_WARNING));
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

require __DIR__ . '/../class/'.str_replace('\\','/',TableArray::class).'.php';
require __DIR__ . '/../class/phpcheck.php';

$t = new PHPcheck;

//call with ?error=1 print only errors
$t->setOutputOnlyErrors(!empty($_GET['error']));
$len = empty($_GET['len']) ? 1000 : (int)($_GET['len']);

//Tests
$t->start('exist versions info');
$info = $t->getClassVersion(TableArray::class);
$t->check($info, !empty($info) AND $info >= "2.6.1");

$t->start('create Object from Array');
$data = [
  0 => ['name' => 'Meier', 'likes' => 3],
  1 => ['name' => 'Lehmann', 'likes' => 6],
  2 => ['name' => 'Schulz','likes' => 14],
];
$TableArray = new TableArray($data);  
//or $sqlObj = TableArray::create($data);
$t->check($TableArray, $TableArray instanceOf TableArray);

$t->start('create Object from Sub-Array and array Key-Path');
$data = [
  'id' => "xp67",
  'result' => [
    'total' => 8, 
    'items' => [
      ['id' => "1", 'name' => "Default"],
      ['id' => "2", 'name' => "PC"]
    ],
  ],
];
$subArr = TableArray::create($data,['result','items'])
  ->fetchAll()
;
$t->checkEqual($subArr, $data['result']['items']);

$t->start('create Object from Sub-Array and string Key-Path');
$data = [
  'id' => "xp67",
  'result' => [
    'total' => 8, 
    'items' => [
      ['id' => "1", 'name' => "Default"],
      ['id' => "2", 'name' => "PC"]
    ],
  ],
];
$subArr = TableArray::create($data,'result.items')
  ->fetchAll()
;
$t->checkEqual($subArr, $data['result']['items']);

//create from 1.dimensional Arrays
$t->start('create from 1.dimensional Array');
$data = ['/image/9x9.png','/image/40x1.png','/upload/40x2.png'];
//Field get the name file
$newData = TableArray::createFromOneDimArray($data)
  ->fetchAll();
$expected = [
  [0,'/image/9x9.png'],
  [1, '/image/40x1.png'],
  [2, '/upload/40x2.png']
];
$t->checkEqual($newData,$expected);

$t->start('create from 1.dimensional Array with keys');
$data = [
  4 => 2.0,
  5 => 3.5,
];
$newData = TableArray::createFromOneDimArray($data)
  ->fetchAll();
$expected = [
  [4, 2.0],
  [5, 3.5],
];
$t->checkEqual($newData,$expected);

$t->start('createFromOneDimArray with row delimter');
$data = [
  '1 line1 5',
  '2 line2 7',
];
$newData = TableArray::createFromOneDimArray($data, " ")
  ->fetchAll();
$expected = [
  ['1',"line1",'5'],
  ['2',"line2",'7'],
];
$t->checkEqual($newData,$expected);

$t->start('createFromOneDimArray with regEx delimter');
$data = [
'1 08:02:12',
'2 09:00:15'
];
$regEx = '/^(\d+) (\d+):(\d+):(\d+)/';
$newData = TableArray::createFromOneDimArray($data,$regEx)
  ->fetchAll()
;
$expected = [
  ['1','08','02','12'],
  ['2','09','00','15'],
];
$t->checkEqual($newData,$expected);

//create from string
$t->start('createFromString with rowDel = " "');
$data = '1 08:11:28
0 08:02:23
1 09:11:24
2 08:00:28';
$newData = TableArray::createFromString($data,' ')
  ->fetchAll()
;
$expected = [
  [ 0 => "1", 1 => "08:11:28"],
  [ 0 => "0", 1 => "08:02:23"],
  [ 0 => "1", 1 => "09:11:24"],
  [ 0 => "2", 1 => "08:00:28"],
];
$t->checkEqual($newData,$expected);

$t->start('createFromString with split , and " "');
$coordinates = "9.499819 123.920318,9.490845 123.916563,9.484644 123.922292";
$newData = TableArray::createFromString($coordinates,' ','/,/')
  ->select("FLOATVAL(0) as lat, FLOATVAL(1) as lng") 
  ->fetchAll()
;
$expected = [
  ['lat' => 9.499819,'lng' => 123.920318],
  ['lat' => 9.490845,'lng' => 123.916563],
  ['lat' => 9.484644,'lng' => 123.922292]
];
$t->checkEqual($newData,$expected);

$t->start('createFromString with rowDel = regEx');
$data = '1 08:11:28
0 08:02:23
1 09:11:24
2 08:00:28';
$newData = TableArray::createFromString($data,'/^(\d+) (\d+)/')
  ->fetchAll()
;
$expected = [
  [ 0 => "1", 1 => "08"],
  [ 0 => "0", 1 => "08"],
  [ 0 => "1", 1 => "09"],
  [ 0 => "2", 1 => "08"],
];
$t->checkEqual($newData,$expected);

$t->start('createFromString with rowDel = regEx and named groups');
$data = '1 08:11
0 08:02
2 09:15';
$regEx = '/^(?<c>\d+) (?<hour>\d+):(?<minute>\d+)/';
$newData = TableArray::createFromString($data, $regEx)
  ->fetchAll()
;
$expected = [
  ['c' => "1", 'hour' => "08", 'minute' => "11"],
  ['c' => "0", 'hour' => "08", 'minute' => "02"],
  ['c' => "2", 'hour' => "09", 'minute' => "15"],
];
$t->checkEqual($newData,$expected);

$t->start('createFromString and split columns with regEx');
$input = 'program    hall    start    end
program0    1     2020-10-02 09:30:00     2020-10-02 10:30:00
program1    2     2020-10-02 11:30:00     2020-10-02 13:30:00';

//2 or more blanks, s is an identifier, does not belong to the regex
$regExSplitColumn = "s/  +/";

$data = TableArray::createFromString($input,$regExSplitColumn)
  ->firstRowToKey()
  ->fetchAll()
;
$expected = [
  ['program' => 'program0', 'hall' => '1', 'start' => '2020-10-02 09:30:00', 'end' => '2020-10-02 10:30:00'],
  ['program' => 'program1', 'hall' => '2', 'start' => '2020-10-02 11:30:00', 'end' => '2020-10-02 13:30:00']
];
$t->checkEqual($data,$expected);

//create from json
$t->start('create Object from JSON-String');
$json = '[{"name":"Meier","likes":3},{"name":"Lehmann","likes":6},{"name":"Schulz","likes":14}]';
$TableArray = TableArray::createFromJson($json);
$t->check($TableArray, $TableArray instanceOf TableArray);

$t->start('create Object from JSONP-String');
$jsonP = "functionCall(".$json.")";
$TableArray = TableArray::createFromJson($jsonP);
$t->check($TableArray, $TableArray instanceOf TableArray);

$t->start('create from JSON with key-Path-string as filter');
$json = '{"data":{"user":[{"name":"Meier","likes":3},{"name":"Lehmann","likes":6}]}}';
$data = TableArray::createFromJson($json,'data.user')
  ->fetchAll()
;
$expected = [
  ['name' => "Meier",'likes' => 3],
  ['name' => "Lehmann",'likes' => 6]
];
$t->checkEqual($data, $expected);

$t->start('create from JSON with filter-function');
$json = '{
  "desc":"user",
  "data": {
    "group1":[
      {"name":"Meier","likes":3},
      {"name":"Lehmann","likes":6}
    ],
    "group2": {"name":"Schulz","likes":14}
   }
}';
$filter = function($cur){
  return array_key_exists('name',$cur) && array_key_exists('likes',$cur);
};
$data = TableArray::createFromJson($json, $filter)
  ->fetchAll()
;
$expected = [
 'data.group1.0' => ['name' => "Meier",'likes' => 3],
 'data.group1.1' => ['name' => "Lehmann",'likes' => 6],
 'data.group2' => ['name' => "Schulz",'likes' => 14]
];
$t->checkEqual($data, $expected);

$t->start('fetch all as JSON-String');
$json = '[{"name":"Meier","likes":3},{"name":"Lehmann","likes":6},{"name":"Schulz","likes":14}]';
$result = TableArray::createFromJson($json)
  ->fetchAllAsJson();
$t->checkEqual($result, $json);

$t->start('json_encode object');
$json = '[{"name":"Meier","likes":3},{"name":"Lehmann","likes":6},{"name":"Schulz","likes":14}]';
$result = json_encode(TableArray::createFromJson($json));
$t->checkEqual($result, $json);

$t->start("create from XML-String");
$strXML = '<?xml version="1.0" encoding="utf-8"?>
<root>
  <row>
    <id>1</id>
    <value>23</value>
  </row>
  <row>
    <id>2</id>
    <value>44</value>
  </row>
</root>';
$xmlTableArray = TableArray::createFromXml($strXML);
$t->check($xmlTableArray, $xmlTableArray instanceOf TableArray);

$t->start('get Array from xmlTableArray');
$result = $xmlTableArray->fetchAll();
$expected = [
  ['id' => '1', 'value' => '23'],
  ['id' => '2', 'value' => '44']
];
$t->checkEqual($result, $expected);

$t->start("create from Simple-XML");
$xml = simplexml_load_string($strXML);
$xmlTableArray = TableArray::createFromXml($xml);
$t->check($xmlTableArray, $xmlTableArray instanceOf TableArray); 

$t->start("create from XML-String with XPath");
$strXML = '<?xml version="1.0" encoding="utf-8"?>
<root>
  <data>
    <row>
      <id>1</id>
      <value>34</value>
    </row>
    <data2>  
      <row>
        <id>2</id>
        <value>66</value>
      </row>
    </data2> 
  </data>
</root>';
$xmlTableArray = TableArray::createFromXml($strXML,'//row');
$result = $xmlTableArray->fetchAll();
$expected = [
  ['id' => '1', 'value' => '34'],
  ['id' => '2', 'value' => '66']
];
$t->checkEqual($result, $expected);

$t->start("create from XML-String with Namespaces");
$strXML = '<?xml version="1.0" encoding="UTF-8"?>
<example xmlns:xyz="http://example.org">
    <xyz:row>
      <id>1</id>
      <value>23</value>
    </xyz:row>
    <xyz:row>
      <id>2</id>
      <value>44</value>
    </xyz:row>
</example>';
$data = TableArray::createFromXml($strXML,'//xyz:row')
  ->fetchAll()
;
$expected = [
  ['id' => '1', 'value' => '23'],
  ['id' => '2', 'value' => '44']
];
$t->checkEqual($data, $expected);

$t->start('create Object from Iterator');
$iterator = function(){
  yield ['name' => 'Meier', 'likes' => 3];
  yield ['name' => 'Lehmann', 'likes' => 6];
  yield ['name' => 'Schulz','likes' => 14];
};
$iterator = $iterator();
$newData = TableArray::create($iterator)
  ->fetchAll();
;
$expected = [
  0 => ['name' => 'Meier', 'likes' => 3],
  1 => ['name' => 'Lehmann', 'likes' => 6],
  2 => ['name' => 'Schulz','likes' => 14],
];
$t->checkEqual($newData, $expected);

$t->start("create from groped array");
$groupedArray = [
  2019 => [
    ['month' => 5, 'profit' => 2345 ],
    ['month' => 6, 'profit' => 134 ]
  ],
  2020 => [
    ['month' => 1, 'profit' => 456 ],
  ]
];
$keys = ['year'];
$newData = TableArray::createFromGroupedArray($groupedArray,$keys)
  ->fetchAll()
;
$expected = [
  ['year' => 2019,'month' => 5, 'profit' => 2345 ],
  ['year' => 2019,'month' => 6, 'profit' => 134 ],
  ['year' => 2020,'month' => 1, 'profit' => 456 ],
];
$t->checkEqual($newData, $expected);

$t->start("create from 2 grouped array");
$groupedArray = [
  2019 => [
    5 => ['profit' => 2345 ],
    6 => ['profit' => 134 ]
  ],
  2020 => [
    1 => ['profit' => 456 ],
  ]
];
$keys = ['year','month'];
$newData = TableArray::createFromGroupedArray($groupedArray,$keys)
  ->fetchAll()
;
$expected = [
  ['year' => 2019,'month' => 5, 'profit' => 2345 ],
  ['year' => 2019,'month' => 6, 'profit' => 134 ],
  ['year' => 2020,'month' => 1, 'profit' => 456 ],
];
$t->checkEqual($newData, $expected);

$t->start('create from CSV-File');
//Simulate CSV-File
$csv = "1;\"2,5 €\"\r\n2;\"3,45 €\"\r\n";
$fileName = $t->simulateFile($csv);

$setOk = TableArray::setCsvDefaultOptions(['delimiter'=>';']);
$data = TableArray::createFromCsvFile($fileName)
  ->fetchAll()
;
$expected = [
  ['1','2,5 €'],
  ['2','3,45 €']
];
$t->check($data, $data == $expected);

$t->start('use object of class in foreach');
$data = [
  ['id' => 1, 'float' => 3.333333333],
  ['id' => 2, 'float' => 13.7777777777],
];
$TableArray = TableArray::create($data)
  ->select('id')
  ->orderBy('id DESC')
;
$newData = [];
foreach($TableArray as $key => $row){
  $newData[$key] = $row;  
}
$expected = [
  ['id' => 2],
  ['id' => 1]
];
$t->checkEqual($newData, $expected);

$t->start('addKeys: put Keys in a new column');
$data = [
  'k1' => ['id' => 1, 'value' => 23],
  'k2' => ['id' => 2, 'value' => 44]
];
$result = TableArray::create($data)
  ->addKeys('key')
  ->fetchRaw()
;
$expected = [
  'k1' => ['id' => 1, 'value' => 23, 'key' => 'k1'],
  'k2' => ['id' => 2, 'value' => 44, 'key' => 'k2']
];
$t->checkEqual($result, $expected);

$t->start('make field to key and remove field');
$data = [
  ['id' => 1, 'value' => 23, 'key' => 'k1'],
  ['id' => 2, 'value' => 44, 'key' => 'k2']
];
$result = TableArray::create($data)
  ->fieldAsKey('key')
  ->fetchRaw()
;
$expected = [
  'k1' => ['id' => 1, 'value' => 23],
  'k2' => ['id' => 2, 'value' => 44]
];
$t->checkEqual($result, $expected);

$t->start('first Row to Key');
$dataFromCSV = [
  ['id','value'],
  [1, 23],
  [2, 44]
];
$result = TableArray::create($dataFromCSV)
  ->firstRowToKey()
  ->fetchRaw();
$expected = [
  ['id' => 1, 'value' => 23],
  ['id' => 2, 'value' => 44]
];
$t->checkEqual($result, $expected);

$t->start('get first Field Name');
$data = [
  ['id' => 1, 'value' => 23],
  ['id' => 2, 'value' => 44]
];
$result = TableArray::create($data)
  ->fieldNameRaw(0);  //0: first
$expected = 'id';
$t->checkEqual($result, $expected);

$t->start('get all Field Names');
$data = [
  ['id' => 1, 'value' => 23],
  ['id' => 2, 'value' => 44]
];
$result = TableArray::create($data)
  ->fieldNameRaw();  //all
$expected = ['id','value'];
$t->checkEqual($result, $expected);

$t->start('reduce multidimensional array to 2 dimensional array');
$data = [
  ['id' => 1, '@attributes' => ['currency' => "USD",'rate' => "1.1370"]],
  ['id' => 2, '@attributes' => ['currency' => "JPY",'rate' => "1.28"]],
];
$newData = TableArray::create($data)
  ->flatten()
  ->fetchAll();
$expected = [
  ['id' => 1, '@attributes.currency' => "USD",'@attributes.rate' => "1.1370"],
  ['id' => 2, '@attributes.currency' => "JPY",'@attributes.rate' => "1.28"],
];
$t->checkEqual($newData, $expected);

$t->start('reduce multidimensional array to 1 dimensional array with flatten and CONCAT');
$warehouses = '[
  {
  "id": 1,
  "name": "warehouse 1",
  "pivot": {
      "product_id": "1",
      "qty": 136.5
      }
  },
  {
  "id": 2,
  "name": "warehouse 2",
  "pivot": {
      "product_id": "1",
      "qty": 71.5
      }
  }
]';
$newData = TableArray::createFromJson($warehouses)
  ->flatten()
  ->select('CONCAT(name," - ",pivot.qty) AS item')
  ->fetchColumn('item')
;
$expected = ["warehouse 1 - 136.5", "warehouse 2 - 71.5"];
$t->checkEqual($newData, $expected);

//collectChilds

$t->start('collect childs recursive');
$data = [
  ['id' => 1, 'val' => 11],
  [
      ['id' => 2, 'val' => 21],
      ['id' => 3, 'val' => 32],
  ],
];
////collects all arrays which have an element with the key 'val' in one level
$newData = TableArray::create($data)
  ->collectChilds(['val'])
  ->fetchAll()
;

$expected = [
  ['id' => 1, 'val' => 11],
  ['id' => 2, 'val' => 21],
  ['id' => 3, 'val' => 32],
];
$t->checkEqual($newData, $expected);

$t->start('collect childs with wildcards');
$data =  [
  ['id' => 1, 'val' => 11],
  ['a' => [
      ['id' => 2, 'val' => 21],
      ['id' => 3, 'val' => 32],
  ]],
  ['b' => [
      ['id' => 4, 'val' => 41],
  ]],
];
$newData = TableArray::create($data)
->collectChilds(['a.*.val'])
->fetchAll()
;
$expected = [
  ['a' => [
    ['id' => 2, 'val' => 21],
    ['id' => 3, 'val' => 32],
  ]]
];
$t->checkEqual($newData, $expected);

$t->start('collect childs and create flatten keys');
$data = [
  'a' => ['id' => 1, 'val' => 11],
  'b' => [
      'b0' => ['id' => 2, 'val' => 21],
      'b1' => ['id' => 3, 'val' => 32],
  ],
];
//collects all arrays which have an element with the key 'val' in one level
$newData = TableArray::create($data)
  ->collectChilds(['val'], true)
  ->fetchAll()
;
$expected = [
  'a' => ['id' => 1, 'val' => 11],
  'b.b0' => ['id' => 2, 'val' => 21],
  'b.b1' => ['id' => 3, 'val' => 32],
];
$t->checkEqual($newData, $expected);

$t->start('#select rows');
$data = [
  ['name' => 'Meier', 'likes' => 3],
  ['name' => 'Lehmann', 'likes' => 6],
  ['name' => 'Schulz','likes' => 14],
];
$newData = TableArray::create($data)
  ->select('name')
  ->fetchAll();
 
$expected = [
  ['name' => 'Meier'],
  ['name' => 'Lehmann'],
  ['name' => 'Schulz'],
];
$t->checkEqual($newData, $expected);

$t->start('select rows and rename');
$data = [
  ['name' => 'Meier', 'likes' => 3],
  ['name' => 'Lehmann', 'likes' => 6],
  ['name' => 'Schulz','likes' => 14],
];
$newData = TableArray::create($data)
  ->select('name AS Surname')
  ->fetchAll();
 
$expected = [
  ['Surname' => 'Meier'],
  ['Surname' => 'Lehmann'],
  ['Surname' => 'Schulz'],
];
$t->checkEqual($newData, $expected);

$t->start('select rows with other column-order');
$data = [
  ['name' => 'Meier', 'likes' => 3],
  ['name' => 'Lehmann', 'likes' => 6],
];
$newData = TableArray::create($data)
  ->select('likes,name')
  ->fetchAll();
 
$expected = [
  ['likes' => 3,'name' => 'Meier'],
  ['likes' => 6,'name' => 'Lehmann'],
];
$t->checkEqual($newData, $expected);

/*
 * Functions
 */

$t->start('select and add column with function UPPER');
$data = [
  ['name' => 'Meier', 'likes' => 3],
  ['name' => 'Lehmann', 'likes' => 6],
];
$newData = TableArray::create($data)
  ->select('name,UPPER(name) as uppername,likes')
  ->fetchAll();
 
$expected = [
 ['name' => 'Meier','uppername' => 'MEIER', 'likes' => 3],
 ['name' => 'Lehmann','uppername' => 'LEHMANN', 'likes' => 6],
];
$t->checkEqual($newData, $expected);

$t->start('select and add column with function LOWER');
$data = [
  ['name' => 'Meier', 'likes' => 3],
  ['name' => 'Lehmann', 'likes' => 6],
];
$newData = TableArray::create($data)
  ->select('name,LOWER(name) as lowername,likes')
  ->fetchAll();
 
$expected = [
 ['name' => 'Meier','lowername' => 'meier', 'likes' => 3],
 ['name' => 'Lehmann','lowername' => 'lehmann', 'likes' => 6],
];
$t->checkEqual($newData, $expected);

$t->start('select and add column with function FORMAT');
$data = [
  ['id' => 1, 'float' => 3.333333333],
  ['id' => 2, 'float' => 13.7777777777],
];
$newData = TableArray::create($data)
  ->select('id,FORMAT("%05.2f",float) as val')
  ->fetchAll();
 
$expected = [
  ['id' => 1, 'val' => "03.33"],
  ['id' => 2, 'val' => "13.78"],
];
$t->checkEqual($newData, $expected);

$t->start('select and concat fields with function FORMAT');
$data = [
  ['id' => 1, 'float' => 3.333333333],
  ['id' => 2, 'float' => 13.7777777777],
];

$newData = TableArray::create($data)
  ->select('id,FORMAT("%d:%05.2f",id,float) as val')
  ->fetchAll();
 
$expected = [
  ['id' => 1, 'val' => "1:03.33"],
  ['id' => 2, 'val' => "2:13.78"],
];
$t->checkEqual($newData, $expected);

$t->start('select and use DATEFORMAT with a datestring');
$testDate = '2021-01-20 14:15:16';
$arr = TableArray::create([['date' => $testDate ]])
  ->select("DATEFORMAT('Y-m-d H:i:s',date) as testDate")
  ->fetchColumn('testDate')
;
$t->checkEqual($arr, [$testDate]);


$t->start('select and use DATEFORMAT with a Timestamp');
$testDate = '2021-01-20 14:15:16';
$timestamp = strtotime($testDate);
$arr = TableArray::create([['ts' => $timestamp ]])
  ->select("DATEFORMAT('Y-m-d H:i:s',ts) as testDate")
  ->fetchColumn('testDate')
;
$t->checkEqual($arr, [$testDate]);

$t->start('select and use DATEFORMAT with a ms-Timestamp');
$testDate = '2022-01-05 14:15:16';
$ms_timestamp = strtotime($testDate) * 1000;
$arr = TableArray::create([['ts' => $ms_timestamp ]])
  ->select("DATEFORMAT('Y-m-d H:i:s',ts,'ms') as testDate")
  ->fetchColumn('testDate')
;
$t->checkEqual($arr, [$testDate]);

$t->start('select and use DATEFORMAT with UTC-Time and a ms-Timestamp');
$testDate = '2021-01-20 14:15:16';
$msTimestamp = strtotime($testDate.'UTC') * 1000;
$arr = TableArray::create([['ts' => $msTimestamp]])
  ->select("DATEFORMAT('Y-m-d H:i:s',ts,'msUTC') as testDate")
  ->fetchColumn('testDate')
;
$t->checkEqual($arr, [$testDate]);



$t->start('select and manipulate fields with REPLACE');
$data = [
  ['id' => 1, 'price' => '4 Eur'],
  ['id' => 2, 'price' => '5 Eur'],
];
$newData = TableArray::create($data)
  ->select('id,REPLACE("Eur","€",price) as price')
  ->fetchAll();
 
$expected = [
  ['id' => 1, 'price' => '4 €'],
  ['id' => 2, 'price' => '5 €'],
];
$t->checkEqual($newData, $expected);

$t->start('use ABS Function in SELECT');
$data = [
  ['value' => -34.7],
  ['value' => 4.6],
];
$newData = TableArray::create($data)
  ->select('ABS(value) as absValue')
  ->fetchAll()
;
$expected = [
  ['absValue' => 34.7],
  ['absValue' => 4.6],
];
$t->checkEqual($newData, $expected);

$t->start('use INTVAL Function ');
$data = [
  ['value' => -34.7],
];
$newData = TableArray::create($data)
  ->select('INTVAL(value) as int')
  ->fetchRow()  //first Row
;
$expected = ['int' => -34];
$t->checkEqual($newData, $expected);

$t->start('use INTVAL Function convert hex');
$data = [
  ['value' => '1c'],
];
$newData = TableArray::create($data)
  ->select('INTVAL(value,"16") as int')
  ->fetchRow()  //first Row
;
$expected = ['int' => 28];
$t->checkEqual($newData, $expected);

$t->start('use INTVAL detection 0x 0b ..');
$data = [
  ['value' => '0x10'],  //16
  ['value' => '10'],
  ['value' => '010'],  //8
  ['value' => '0b110']  //6
];
$newData = TableArray::create($data)
  ->select('INTVAL(value,"0") as int')
  ->fetchAll()  
;
$expected = [
  ['int' => 16], 
  ['int' => 10],
  ['int' => 8], 
  ['int' => 6] 
];
$t->checkEqual($newData, $expected);

$t->start('FLOATVAL from number-format');
$data = [
  ['price' => '23.123,87']  //23123.87
];
$newData = TableArray::create($data)
  ->select("FLOATVAL(price,',','.') AS priceval")
  ->fetchRow();
  $expected = ['priceval' => 23123.87];
$t->checkEqual($newData, $expected);

$t->start('select and use FLOATVAL and SUBSTR');
$data = [
  ['id' => 1, 'price' => '4.5 €'],
  ['id' => 2, 'price' => '5 $'],
];
$newData = TableArray::create($data)
  ->select("id, FLOATVAL(price) AS priceval, SUBSTR(price,'-1') AS currency")
  ->fetchAll();
 
$expected = [
  ['id' => 1, 'priceval' => 4.5, 'currency' => '€'],
  ['id' => 2, 'priceval' => 5, 'currency' => '$'],
];
$t->check($newData, $newData == $expected);

$t->start('select and use TRIM');
$data = [
  ['id' => 1, 'price' => '4.5 €'],
  ['id' => 2, 'price' => '5 $'],
];
$newData = TableArray::create($data)
  ->select("id, TRIM(price,' €$') AS price")
  ->fetchAll();
 
$expected = [
  ['id' => 1, 'price' => '4.5'],
  ['id' => 2, 'price' => '5', ],
];
$t->check($newData, $newData == $expected);

$t->start('select and use SCALE with a factor');
$data = [
  ['id' => 1, 'value' => 45],
  ['id' => 2, 'value' => 35],
];
$newData = TableArray::create($data)
  ->select("id, SCALE(value,'0.1') AS val")
  ->fetchAll();
 
$expected = [ //val = value * 0.1
  ['id' => 1, 'val' => 4.5],
  ['id' => 2, 'val' => 3.5],
];
$t->check($newData, $newData == $expected);

$t->start('select and use SCALE with a factor and add');
$data = [ // val = value *0.01 + 0.002
  ['id' => 1, 'value' => 245],
  ['id' => 2, 'value' => 635],
];
$newData = TableArray::create($data)
  ->select("id, SCALE(value,'0.01','0.002') AS val")
  ->fetchAll();
 
$expected = [ //val = value * 0.1
  ['id' => 1, 'val' => 2.452],
  ['id' => 2, 'val' => 6.352],
];
$t->check($newData, $newData == $expected);

$t->start('SCALE with a factor, add and format');
$data = [ // val = value *0.01 + 0.002
  ['id' => 1, 'value' => 245],
  ['id' => 2, 'value' => 635],
];
$newData = TableArray::create($data)
  ->select("id, SCALE(value,'0.01','0.002','%.2f') AS val")
  ->fetchAll();
 
$expected = [ //val = value * 0.1
  ['id' => 1, 'val' => 2.45],
  ['id' => 2, 'val' => 6.35],
];
$t->check($newData, $newData == $expected);

$t->start('NULLCOUNT');
$data = [ 
  ['id' => 1, 'value' => 245, 'value2' => 55],
  ['id' => 2, 'value' => 245, 'value2' => null],
  ['id' => 3, 'value' => null, 'value2' =>null],
];
$newData = TableArray::create($data)
  ->select("id, NULLCOUNT(id,value,value2) AS nullRows")
  ->fetchAll();

$expected = [
  ['id' => 1,'nullRows' => 0],
  ['id' => 2,'nullRows' => 1],
  ['id' => 3,'nullRows' => 2],
];
$t->check($newData, $newData == $expected);

$t->start('CONCAT');
$data = [ 
  ['id' => 1, 'value' => 'x', 'value2' => 55],
  ['id' => 2, 'value' => 'Z', 'value2' => null],
  ['id' => 3, 'value' => 's', 'value2' => 't'],
];
$newData = TableArray::create($data)
  ->select("id, CONCAT(value,value2) AS concatval")
  ->fetchAll();

$expected = [
  ['id' => 1,'concatval' => 'x55'],
  ['id' => 2,'concatval' => 'Z'],
  ['id' => 3,'concatval' => 'st'],
];
$t->check($newData, $newData == $expected);

$t->start('select multiple times');
$data = [
  ['id' => "1",'date' => "2020-04-01",'time' => "14:03",'userId' => 14, 'action' => "login"],
  ['id' => "2",'date' => "2020-04-01",'time' => "14:45",'userId' => 14, 'action' => "logout"],
];

$newData = TableArray::create($data)
  ->SELECT("CONCAT(date,' ',time) AS datetime") //date + time -> datetime
  ->SELECT("userId,action,DATEFORMAT('d.m.Y H:i:s',datetime) as date")
  ->fetchAll()
;
$expected = [
  ['userId' => 14, 'action' => "login", 'date' => "01.04.2020 14:03:00"],
  ['userId' => 14, 'action' => "logout", 'date' => "01.04.2020 14:45:00"],
];
$t->check($newData, $newData == $expected);

$t->start('SPLIT price and currency');
$data = [
  ['1','2.5 €'],
  ['2','3.45 €']
];
$newData = TableArray::create($data)
  ->select("0 as id, SPLIT(1,' ','0') AS price, SPLIT(1,' ','1') as currency")
  ->fetchAll()
;
$expected = [
  ['id' => '1','price' => '2.5', 'currency' => '€'],
  ['id' => '2','price' => '3.45', 'currency' => '€'],
];
$t->checkEqual($newData, $expected);


$t->start('Create a list from array with implode');
$data = [
  ['name' => 'scool 25', 'school_subjects' => ['English','maths','science']],
  ['name' => 'scool 26', 'school_subjects' => ['English','maths','history']],
];
$newData = TableArray::create($data)
  ->select("name, IMPLODE(school_subjects) AS subjectlist")
  ->FilterLikeIn('subjectlist','science')
  ->fetchAll();

$expected = [
  ['name' => 'scool 25', 'subjectlist' => 'English,maths,science'],
];
$t->check($newData, $newData == $expected);

$t->start('list from array recursive with implode');
$data = [
  ['id' => 1, 'values' => ['A',['B','C']]]
]; 
$newData = TableArray::create($data)
  ->select("IMPLODE(values) AS letter")
  ->fetchRow()
;
$expected = ['letter' => "A,B,C"];
$t->checkEqual($newData, $expected);


$t->start('Use PHP-Function as user-Funktion');
$data = [
  ['id' => 1, 'name' => 'abc'],
  ['id' => 2, 'name' => 'bla'],
];
$newData = TableArray::create($data)
  ->addSqlFunction('MD5','md5')
  ->select("id, name, MD5(name) AS hash")
  ->fetchAll();
 
$expected = [
  ['id' => 1, 'name' => 'abc', 'hash' => md5('abc')],
  ['id' => 2, 'name' => 'bla', 'hash' => md5('bla')]
];
$t->check($newData, $newData == $expected);

$t->start('select and add column with user-function');
$data = [
  ['id' => 1, 'val' => 3, 'factor' => 4.5],
  ['id' => 1, 'val' => 6, 'factor' => 1.0],
];
$newData = TableArray::create($data)
  ->addSqlFunction('mul',function($v1,$v2){return $v1*$v2;})
  ->select('id,val,factor,mul(val,factor) as product')
  ->fetchAll();
 
$expected = [
  ['id' => 1, 'val' => 3, 'factor' => 4.5, 'product' => 13.5],
  ['id' => 1, 'val' => 6, 'factor' => 1.0, 'product' => 6.0],
];
$t->checkEqual($newData, $expected);

$t->start('select and add column with user-function with use');
$data = [
  ['id' => 1, 'val' => 3],
  ['id' => 1, 'val' => 6],
];

$factor = 2;
$newData = TableArray::create($data)
  ->addSqlFunction('mul',function($val) use($factor){return $val * $factor;})
  ->select('id,val,mul(val) as product')
  ->fetchAll();
 
$expected = [
  ['id' => 1, 'val' => 3, 'product' => 6],
  ['id' => 1, 'val' => 6, 'product' => 12],
];
$t->checkEqual($newData, $expected);

$t->start('select with accumulated sum in extra column');
$data = [
  ['id' => 1, 'val' => 3],
  ['id' => 2, 'val' => 6],
  ['id' => 3, 'val' => 4],
];
$sum = 0;
$newData = TableArray::create($data)
  ->addSqlFunction('sum',function($val) use(&$sum){
      $sum += $val;
      return $sum;
    })
  ->select('val,sum(val) as sum')
  ->fetchAll();
$expected = [
  ['val' => 3, 'sum' => 3],
  ['val' => 6, 'sum' => 9],
  ['val' => 4, 'sum' => 13],
];  
$t->checkEqual($newData, $expected);

$t->start('add Functions with array');
$fileFunctions = [
  'BASENAME' => 'basename',
  'DIRNAME'  => 'dirname',
];
$TableArray = TableArray::create()
  ->addSqlFunctionFromArray($fileFunctions)
;
$checkOk = $TableArray->getSqlFunction('BASENAME') !== false AND 
  $TableArray->getSqlFunction('DIRNAME') !== false;
$t->checkEqual($checkOk, true);

$t->start('#orderBy : simple sort string ASC');
$data = [
  ['name' => 'Meier', 'likes' => 3],
  ['name' => 'Lehmann', 'likes' => 6],
  ['name' => 'Schulz','likes' => 14],
];
$newData = TableArray::create($data)
  ->orderBy('name')
  ->fetchAll();
 
$expected = [
   ['name' => 'Lehmann', 'likes' => 6],
   ['name' => 'Meier', 'likes' => 3],
   ['name' => 'Schulz','likes' => 14],
];
$t->checkEqual($newData, $expected);

$t->start('select and sort string ASC');
$data = [
  ['name' => 'Meier', 'likes' => 3],
  ['name' => 'Lehmann', 'likes' => 6],
  ['name' => 'Schulz','likes' => 14],
];
$newData = TableArray::create($data)
  ->select('likes')
  ->orderBy('name')
  ->fetchAll();
 
$expected = [
  ['likes' => 6],
  ['likes' => 3],
  ['likes' => 14],
];
$t->checkEqual($newData, $expected);


$t->start('simple sort string DESC');
$newData = TableArray::create($data)
  ->orderBy('name DESC')
  ->fetchAll();
 
$expected = [
  ['name' => 'Schulz','likes' => 14],
  ['name' => 'Meier', 'likes' => 3],
  ['name' => 'Lehmann', 'likes' => 6],
];
$t->checkEqual($newData, $expected);

$t->start('simple sort number DESC');
$newData = TableArray::create($data)
  ->orderBy('likes DESC')
  ->fetchAll();
$expected = [
  ['name' => 'Schulz','likes' => 14],
  ['name' => 'Lehmann', 'likes' => 6],
  ['name' => 'Meier', 'likes' => 3],
];
$t->checkEqual($newData, $expected);

$t->start('sort NATURAL');
$data = [
  ['name' => 'A1', 'likes' => 3],
  ['name' => 'A12', 'likes' => 6],
  ['name' => 'A2','likes' => 14],
  ['name' => 'A14','likes' => 7],
];
$newData = TableArray::create($data)
  ->orderBy('name NATURAL')
  ->fetchAll();
$expected = [
  ['name' => 'A1', 'likes' => 3],
  ['name' => 'A2','likes' => 14],
  ['name' => 'A12', 'likes' => 6],
  ['name' => 'A14','likes' => 7],
];
$t->checkEqual($newData, $expected);

$t->start('sort 2 criteria');
$data = [
  ['name' => 'A', 'likes' => 3],
  ['name' => 'B', 'likes' => 6],
  ['name' => 'A','likes' => 14],
];
$newData = TableArray::create($data)
  ->orderBy('name,likes')
  ->fetchAll();
$expected = [
  ['name' => 'A', 'likes' => 3],
  ['name' => 'A','likes' => 14],
  ['name' => 'B', 'likes' => 6],
];
$t->checkEqual($newData, $expected);

$t->start('sort first ASC, second DESC');
$data = [
  ['name' => 'A', 'likes' => 3],
  ['name' => 'B', 'likes' => 6],
  ['name' => 'A','likes' => 14],
];
$newData = TableArray::create($data)
  ->orderBy('name ASC,likes DESC')
  ->fetchAll();
$expected = [
  ['name' => 'A','likes' => 14],
  ['name' => 'A', 'likes' => 3],
  ['name' => 'B', 'likes' => 6],
];
$t->checkEqual($newData, $expected);

$t->start('sort num. Array first ASC, second DESC');
$data = [
  ['A', 3],
  ['B', 6],
  ['A', 14],
];
$newData = TableArray::create($data)
  ->orderBy('0 ASC,1 DESC')
  ->fetchAll();
$expected = [
  ['A', 14],
  ['A',  3],
  ['B', 6],
];
$t->checkEqual($newData, $expected);

$t->start('sort with LIKE-function');
$data = [
  ['name' => 'A', 'pos' => '2'],
  ['name' => 'B', 'pos' => '3.ceo'],
  ['name' => 'C','pos' => '10'],
];
$newData = TableArray::create($data)
  ->orderBy("LIKE(pos,'%CEO%') DESC,pos ASC")
  ->fetchAll();
$expected = [
  ['name' => 'B', 'pos' => '3.ceo'],
  ['name' => 'A', 'pos' => '2'],
  ['name' => 'C','pos' => '10'],
];
$t->checkEqual($newData, $expected);

$t->start('sort with DATEFORMAT-function');
$data = [
  ['name' => 'A', 'date' => '1.1.2018'],
  ['name' => 'B', 'date' => '23.6.2017'],
  ['name' => 'C','date' => '31.1.2006'],
];
$newData = TableArray::create($data)
  ->orderBy("DATEFORMAT('Y-m-d',date) ASC")
  ->fetchAll();
$expected = [
  ['name' => 'C','date' => '31.1.2006'],
  ['name' => 'B', 'date' => '23.6.2017'],
  ['name' => 'A', 'date' => '1.1.2018'],
];
$t->checkEqual($newData, $expected);

$t->start('sort with user Function');
$data = [
  ['name' => 'A', 'pos' => '2'],
  ['name' => 'B', 'pos' => '1.ceo'],
  ['name' => 'C','pos' => '1'],
];
$newData = TableArray::create($data)
  ->addSqlFunction('ceoFirst',function($val){return (stripos($val,'ceo') !== false ? "A" : "B");})
  ->orderBy('ceoFirst(pos),pos')
  ->fetchAll();
$expected = [
  ['name' => 'B', 'pos' => '1.ceo'],
  ['name' => 'C','pos' => '1'],
  ['name' => 'A', 'pos' => '2'],
];
$t->checkEqual($newData, $expected);

$t->start('sort with user Function name C,D,B,A');
$data = [
  ['name' => 'A', 'pos' => '2'],
  ['name' => 'B', 'pos' => '1.ceo'],
  ['name' => 'C','pos' => '1'],
  ['name' => 'D','pos' => '3'],
];
$newData = TableArray::create($data)
  ->addSqlFunction('cdba',function($val){
      $atFirst = ["C","D","B","A"];
      $i = array_search($val,$atFirst);
      return $i !== false ? $i : 999;
    })
  ->orderBy('cdba(name)')
  ->fetchAll();
$expected = [
  ['name' => 'C','pos' => '1'],
  ['name' => 'D','pos' => '3'],
  ['name' => 'B', 'pos' => '1.ceo'],
  ['name' => 'A', 'pos' => '2'],
];
$t->checkEqual($newData, $expected);

$t->start('complex example');
$refData = [
  "Ref0",
  "Ref1",
  "Z-Ref2",
  "A-Ref3"
];
$data = [
  ['name' => 'A', 'ref' => 2],
  ['name' => 'B', 'ref' => 3],
];
$newData = TableArray::create($data)
  ->addSqlFunction('refFrom',function($val)use($refData){return $refData[$val];})
  ->select('name,ref,refFrom(ref) as refVal')
  ->orderBy('refVal')
  ->fetchAll();
  
$expected = [  
  ['name' => "B", 'ref' => 3, 'refVal' => "A-Ref3"],
  ['name' => "A", 'ref' => 2, 'refVal' => "Z-Ref2"],
 ];
$t->checkEqual($newData, $expected);

$t->start('fetchKeyValue');
$data = [
  ['id' => "1",'name' => "monitor",'quantity' => "1",'orderId' => "1"],
  ['id' => "2",'name' => "headset",'quantity' => "5",'orderId' => "1"],
  ['id' => "3",'name' => "PC",'quantity' => "2",'orderId' => "2"],
  ['id' => "4",'name' => "Maus",'quantity' => "3",'orderId' => "2"],
];
$newData = TableArray::create($data)->fetchKeyValue('id','name');
$expected = [
  1 => "monitor",
  2 => "headset",
  3 => "PC",
  4 => "Maus",
];
$t->checkEqual($newData, $expected);

$t->start('example createFromOneDimArray and fetchKeyValue');
$dates = [
   "2019-09-11" => 1,
   "2019-10-12" => 3,
   "2019-09-14" => 4,
];
$newData = TableArray::createFromOneDimArray($dates)
  ->select('DATEFORMAT("M Y",0) AS group, 1')
  ->filterGroupAggregate([1 => 'sum'],['group'])
  ->orderBy('DATEFORMAT("Y-m",group) DESC')
  ->fetchKeyValue('group',1)
;
$expected = [
  'Oct 2019' => 3,
  'Sep 2019' => 5,
];
$t->checkEqual($newData, $expected);

$t->start('fetchRow without Key');
$data = [
  ['id' => "1",'name' => "monitor"],
  ['id' => "2",'name' => "headset"],
];
$newData = TableArray::create($data)
  ->select('name')
  ->fetchRow()
;
$expected = ['name' => "monitor"];  //first row
$t->checkEqual($newData, $expected);

$t->start('fetchRow with Key');
$data = [
  ['id' => "1",'name' => "monitor"],
  ['id' => "2",'name' => "headset"],
];
$newData = TableArray::create($data)
  ->fetchRow(1) //second row
;
$expected = ['id' => "2",'name' => "headset"]; 
$t->checkEqual($newData, $expected);

$t->start('fetchRow with unknown Key');
$data = [
  ['id' => "1",'name' => "monitor"],
  ['id' => "2",'name' => "headset"],
];
$newData = TableArray::create($data)
  ->fetchRow(5) 
;
$expected = false; 
$t->checkEqual($newData, $expected);

$t->start('fetchColumn');
$data = [
  ['id' => "1",'name' => "monitor",'quantity' => "1",'orderId' => "1"],
  ['id' => "2",'name' => "headset",'quantity' => "5",'orderId' => "1"],
  ['id' => "3",'name' => "PC",'quantity' => "2",'orderId' => "2"],
  ['id' => "4",'name' => "Maus",'quantity' => "3",'orderId' => "2"],
];
$newData = TableArray::create($data)->fetchColumn('quantity');
$expected = ["1","5","2","3"];
$t->checkEqual($newData, $expected);

$t->start('fetchColumnUnique');
$data = [
  ['id' => "1",'name' => "monitor",'quantity' => "1",'orderId' => "1"],
  ['id' => "2",'name' => "headset",'quantity' => "5",'orderId' => "1"],
  ['id' => "3",'name' => "monitor",'quantity' => "2",'orderId' => "2"],
];
$newData = TableArray::create($data)->fetchColumnUnique('name');
$expected = ["headset","monitor"];
$t->checkEqual($newData, $expected);


$t->start('fetchGroup');
$data = [
  ['id' => "1",'name' => "monitor",'quantity' => "1",'orderId' => "1"],
  ['id' => "2",'name' => "headset",'quantity' => "5",'orderId' => "1"],
  ['id' => "3",'name' => "PC",'quantity' => "2",'orderId' => "2"],
  ['id' => "4",'name' => "Maus",'quantity' => "3",'orderId' => "2"],
];
$newData = TableArray::create($data)->fetchGroup(['orderId']);
$expected = [
  1 => [
    0 => ['id' => "1",'name' => "monitor",'quantity' => "1",'orderId' => "1"],
    1 => ['id' => "2",'name' => "headset",'quantity' => "5",'orderId' => "1"],
  ],
  2 => [
    2 =>['id' => "3",'name' => "PC",'quantity' => "2",'orderId' => "2"],
    3 =>['id' => "4",'name' => "Maus",'quantity' => "3",'orderId' => "2"],
  ]
];
$t->checkEqual($newData, $expected);

$t->start('concat two fields and fetchGroup');
$data = [
  ['id' => "1",'date' => "2020-04-01",'time' => "14:45",'userId' => 1, 'action' => "login"],
  ['id' => "2",'date' => "2020-04-01",'time' => "14:45",'userId' => 2, 'action' => "logout"],
  ['id' => "3",'date' => "2020-04-01",'time' => "16:33",'userId' => 1, 'action' => "logout"],
];

$newData = TableArray::create($data)
  ->SELECT("id, userId, action, CONCAT(date,' ',time) AS datetime")
  ->fetchgroup(['datetime'])
;
$expected = [
  '2020-04-01 14:45' => [
    0 => ['id' => "1",'userId' => 1, 'action' => "login", 'datetime' => "2020-04-01 14:45"],
    1 => ['id' => "2",'userId' => 2, 'action' => "logout", 'datetime' => "2020-04-01 14:45"],
  ],
  '2020-04-01 16:33' => [
    2 => ['id' => "3",'userId' => 1, 'action' => "logout", 'datetime' => "2020-04-01 16:33"],
  ]
];
$t->checkEqual($newData, $expected);

$t->start('fetchGroup 2 groups');
$data = [
  ['id' => "1",'name' => "monitor",'cat' => "A",'orderId' => "1"],
  ['id' => "2",'name' => "headset",'cat' => "B",'orderId' => "1"],
  ['id' => "3",'name' => "PC",'cat' => "A",'orderId' => "2"],
  ['id' => "4",'name' => "Maus",'cat' => "A",'orderId' => "2"],
];
$newData = TableArray::create($data)->fetchGroup(['orderId','cat']);
$expected = [
  1 => [
    'A' => [
        0 => ['id' => "1",'name' => "monitor",'cat' => "A",'orderId' => "1"]
    ],
    'B' => [
        1 => ['id' => "2",'name' => "headset",'cat' => "B",'orderId' => "1"]
    ],
  ],
  2 => [
    'A' => [
       2 =>['id' => "3",'name' => "PC",'cat' => "A",'orderId' => "2"],
       3 =>['id' => "4",'name' => "Maus",'cat' => "A" ,'orderId' => "2"]
    ]
  ]
];
$t->checkEqual($newData, $expected);


$t->start('data with objects');
$data = [ 
  (object)['name' => 'Meier', 'likes' => 3], 
  (object)['name' => 'Lehmann', 'likes' => 6], 
  (object)['name' => 'Schulz','likes' => 14], 
]; 
$sqlObj = TableArray::create($data);

$expected = [ 
  ['name' => 'Meier', 'likes' => 3], 
  ['name' => 'Lehmann', 'likes' => 6], 
  ['name' => 'Schulz','likes' => 14], 
]; 

$t->checkEqual($sqlObj->fetchAll(), $expected);

$t->start('get array of objects');
$data = [ 
  ['name' => 'Meier', 'likes' => 3], 
  ['name' => 'Lehmann', 'likes' => 6], 
  ['name' => 'Schulz','likes' => 14], 
]; 
$newData = TableArray::create($data)->fetchAllObj();

$expected = [ 
  (object)['name' => 'Meier', 'likes' => 3], 
  (object)['name' => 'Lehmann', 'likes' => 6], 
  (object)['name' => 'Schulz','likes' => 14], 
]; 

$t->check($newData, $newData == $expected);

$t->start('fetchAllAsCsv with default options');
$data = [
  ['id' => '1','price' => '2,5 €'],
  ['id' => '2','price' => '3,45 €']
];
TableArray::setCsvDefaultOptions(['delimiter'=>';']);
$csv = TableArray::create($data)
  ->fetchAllAsCsv()
;
//BOM at first, Semicolon as delimiter, "\r\n" as eol was set
$expected = "\xef\xbb\xbf1;\"2,5 €\"\r\n2;\"3,45 €\"\r\n";
$t->checkEqual($csv,$expected);

$t->start('fetchAllAsCsv with options');
$data = [
  ['id' => '1','price' => '2,5 €'],
  ['id' => '2','price' => '3,45 €']
];
$csv = TableArray::create($data)
  ->setOption([
    'title'=>true,
    'bom' => false,
    'delimiter' => ",",
    'eol' => "\n"
  ])
  ->fetchAllAsCsv()
;
$expected = 	"id,price\n1,\"2,5 €\"\n2,\"3,45 €\"\n";
$t->checkEqual($csv,$expected);

//Limit and Offset
$t->start('offset');
$data = [
  ['A', 3],
  ['B', 6],
  ['A', 14],
];
$newData = TableArray::create($data)
  ->offset(1)  //remove 1.row from table-Object
  ->fetchAll();
$expected = [
  //['A', 3],
  ['B', 6],
  ['A', 14],
];
$t->checkEqual($newData, $expected);

$t->start('limit');
$data = [
  ['A', 3],
  ['B', 6],
  ['A', 14],
];
$newData = TableArray::create($data)
  ->limit(2) //remove 3.row from table-Object
  ->fetchAll();
$expected = [
  ['A', 3],
  ['B', 6],
  //['A', 14],
];
$t->checkEqual($newData, $expected);

$t->start('limit from end');
$data = [
  ['A', 3],
  ['B', 6],
  ['A', 14],
];
$newData = TableArray::create($data)
  ->limit(-1) //get last row
  ->fetchAll();
$expected = [
  //['A', 3],
  //['B', 6],
  ['A', 14],
];
$t->checkEqual($newData, $expected);

$t->start('limit with fetch');
$data = [
  ['A', 3],
  ['B', 6],
  ['A', 14],
];
$newData = TableArray::create($data)
  ->fetchLimit(2);
$expected = [
  ['A', 3],
  ['B', 6],
];
$t->checkEqual($newData, $expected);

$t->start('limit with fetch and start');
$data = [
  ['A', 3],
  ['B', 6],
  ['C', 14],
];
$newData = TableArray::create($data)
  ->fetchLimit(2,1);  //Limit 2, Start 1
$expected = [
  ['B', 6],
  ['C', 14],
];
$t->checkEqual($newData, $expected);

$t->start('limit with fetch, start and keep keys');
$data = [
  0 => ['A', 3],
  1 => ['B', 6],
  2 => ['C', 14],
];
$newData = TableArray::create($data)
  ->fetchLimit(2,1,true);  //Limit 2, Start 1, keep keys
$expected = [
 1 => ['B', 6],
 2 => ['C', 14],
];
$t->checkEqual($newData, $expected);

$t->start('limit with fetch from end');
$data = [
  ['A', 3],
  ['B', 6],
  ['A', 14],
];
$newData = TableArray::create($data)
  ->fetchLimitFromEnd(1);  //get last
$expected = [
  ['A', 14]
];
$t->checkEqual($newData, $expected);

$t->start('offset + limit');
$data = [
  ['A', 3],
  ['B', 6],
  ['A', 14],
];
$newData = TableArray::create($data)
  ->offset(1)
  ->limit(1)
  ->fetchAll();
$expected = [
  //['A', 3],
  ['B', 6],
  //['A', 14],
];
$t->checkEqual($newData, $expected);

//pivot
$t->start('pivot');
$data = [
['group' => 1, 'case' => 1, 'value' => 11],
['group' => 1, 'case' => 2, 'value' => 22],
];
$newData = TableArray::create($data)
  ->pivot('group','value','case')
  ->fetchAll();
$expected = [
  1 => ['group' => 1, 'value.1' => 11, 'value.2' => 22]
];
$t->checkEqual($newData, $expected);

//pivot
$t->start('pivot and select');
$data = [
['group' => 1, 'case' => 1, 'value' => 11],
['group' => 1, 'case' => 2, 'value' => 22],
['group' => 1, 'case' => 3, 'value' => 33],
];
$newData = TableArray::create($data)
  ->pivot('group','value','case')
  ->select('value.1 as v1, value.3 as v3')
  ->fetchAll();
$expected = [
  1 => ['v1' => 11, 'v3' => 33]
];
$t->checkEqual($newData, $expected);

//merge
$t->start('merge 2 arrays with same table structure');
$data1 = [
  ['id' => 1, 'name' => 'name1', 'refId' => 1],
  ['id' => 2, 'name' => 'name2', 'refId' => 2],
];

$data2 = [
  ['id' => 3, 'name' => 'name3', 'refId' => 3],
];
$newData = TableArray::create($data1)
  ->merge($data2)
  ->fetchAll()
;
$expected = [
  ['id' => 1, 'name' => 'name1', 'refId' => 1],
  ['id' => 2, 'name' => 'name2', 'refId' => 2],
  ['id' => 3, 'name' => 'name3', 'refId' => 3],
];
$t->checkEqual($newData, $expected);

$t->start('merge 2 arrays with different table structure');
$data1 = [
  ['id' => 1, 'name' => 'name1'],
  ['id' => 2, 'name' => 'name2'],
];

$data2 = [
  ['id' => 1, 'refId' => 1],
];
$newData = TableArray::create($data1)
  ->merge($data2)
  ->fetchAll()
;
$expected = [
  ['id' => 1, 'name' => 'name1', 'refId' => NULL],
  ['id' => 2, 'name' => 'name2', 'refId' => NULL],
  ['id' => 1, 'name' => NULL, 'refId' => 1],
];
$t->checkEqual($newData, $expected);

$t->start('merge 2 arrays with string row keys');
$data1 = [
  'id1' =>  ['name' => 'name1'],
  'id2' =>  ['name' => 'name2'],
];
  
$data2 = [
  'id1' => ['refId' => 1],
];
$newData = TableArray::create($data1)
  ->merge($data2)
  ->fetchAll()
;
$expected = [
  'id1' =>  ['name' => 'name1', 'refId' => 1],
  'id2' =>  ['name' => 'name2', 'refId' => NULL],
];
$t->checkEqual($newData, $expected);

//inner Join
$t->start('inner Join');
$data=[
  ['id' => 1, 'name' => 'name1', 'refId' => 1],
  ['id' => 2, 'name' => 'name2', 'refId' => 2],
  ['id' => 3, 'name' => 'name3', 'refId' => 3],
];
$ref=[
  ['id' => 1, 'c' => 'ref1', 'd' => 'A'],
  ['id' => 2, 'c' => 'ref2', 'd' => 'B']
];
$newData = TableArray::create($data)
  ->innerJoinOn($ref,'t2','id','refId')
  ->fetchAll();

$expected=[
  ['id' => 1, 'name' => 'name1', 
    'refId' => 1, 't2.c' => 'ref1', 't2.d' => 'A'],
  ['id' => 2, 'name' => 'name2', 
    'refId' => 2, 't2.c' => 'ref2', 't2.d' => 'B'],
];
$t->checkEqual($newData, $expected);

$t->start('inner Join without TableAlias');
$data=[
  ['id' => 1, 'name' => 'name1', 'refId' => 1],
  ['id' => 2, 'name' => 'name2', 'refId' => 2],
  ['id' => 3, 'name' => 'name3', 'refId' => 3],
];
$ref=[
  ['id' => 1, 'c' => 'ref1', 'd' => 'A'],
  ['id' => 2, 'c' => 'ref2', 'd' => 'B']
];
$newData = TableArray::create($data)
  ->innerJoinOn($ref,'','id','refId')
  ->fetchAll();

$expected=[
  ['id' => 1, 'name' => 'name1', 
    'refId' => 1, 'c' => 'ref1', 'd' => 'A'],
  ['id' => 2, 'name' => 'name2', 
    'refId' => 2, 'c' => 'ref2', 'd' => 'B'],
];
$t->checkEqual($newData, $expected);

//left Join
$t->start('left Join');
$data=[
  ['id' => 1, 'name' => 'name1', 'refId' => 1],
  ['id' => 2, 'name' => 'name2', 'refId' => 2],
  ['id' => 3, 'name' => 'name3', 'refId' => 3],
];
$ref=[
  ['id' => 1, 'c' => 'ref1', 'd' => 'A'],
  ['id' => 2, 'c' => 'ref2', 'd' => 'B']
];
$newData = TableArray::create($data)
  ->leftJoinOn($ref,'t2','id','refId')
  ->fetchAll();
  
$expected=[
  ['id' => 1, 'name' => 'name1', 
    'refId' => 1, 't2.c' => 'ref1', 't2.d' => 'A'],
  ['id' => 2, 'name' => 'name2', 
    'refId' => 2, 't2.c' => 'ref2', 't2.d' => 'B'],
  ['id' => 3, 'name' => 'name3', 
    'refId' => 3, 't2.c' => null, 't2.d' => null],
];
$t->checkEqual($newData, $expected);

//inner Join, select and order
$t->start('inner Join, select, order');
$data=[
  ['id' => 1, 'name' => 'name1', 'refId' => 1],
  ['id' => 2, 'name' => 'name2', 'refId' => 2],
  ['id' => 3, 'name' => 'name3', 'refId' => 3],
];
$ref=[
  ['id' => 1, 'c' => 'ref1', 'd' => 'A'],
  ['id' => 2, 'c' => 'ref2', 'd' => 'X'],
  ['id' => 3, 'c' => 'ref3', 'd' => 'C']
];
$newData = TableArray::create($data)
  ->innerJoinOn($ref,'t2','id','refId')
  ->select('id, name, t2.d')
  ->orderBy('t2.d DESC')
  ->limit(1)
  ->fetchAll();

$expected=[
  ['id' => 2, 'name' => 'name2', 't2.d' => 'X'],
];
$t->checkEqual($newData, $expected);

$t->start('multiple Join');
//input
$data=[ 
  ['id' => 1, 'name' => 'name1', 'refId' => 1], 
  ['id' => 2, 'name' => 'name2', 'refId' => 2], 
]; 
$refA=[ 
  ['id' => 1, 'key' => 'k1'], 
  ['id' => 2, 'key' => 'k2'] 
]; 
$refB=[ 
  ['id' => 'k1', 'name' => 'Hardware'], 
  ['id' => 'k2', 'name' => 'Software'] 
]; 

//join and select
$tabArr_A = TableArray::create($refA)
->innerJoinOn($refB,'refb','id','key')
->select('id,refb.name as name')
;

$newData = TableArray::create($data) 
  ->innerJoinOn($tabArr_A,'t2','id','refId')
  ->select('id,t2.name as fullname')
  ->fetchAll()
;

$expected = [
  [ 'id' => 1, 'fullname' => 'Hardware'],
  [ 'id' => 2, 'fullname' => 'Software'],
]; 
$t->checkEqual($newData, $expected);

/*
* Filter
*/
$t->start('#filter Equal: where size = large');
$data = [
  ["id" => "123", "sku" => "MED_BL_DRESS", "size" => "medium", "color" => "black"],
  ["id" => "321", "sku" => "LG_GR_DRESS", "size" => "large", "color" => "green"],
  ["id" => "31321", "sku" => "LG_RD_DRESS", "size" => "large", "color" => "red"]
];
$newData = TableArray::create($data)
  ->filterEqual('size', 'large')
  ->fetchAll()
;
$expected = [
  1 => ["id" => "321", "sku" => "LG_GR_DRESS", "size" => "large", "color" => "green"],
  2 => ["id" => "31321", "sku" => "LG_RD_DRESS", "size" => "large", "color" => "red"]
];
$t->checkEqual($newData, $expected);

$t->start('filterEqual: [size => large]');
$newData = TableArray::create($data)
  ->filterEqual(['size' => 'large'])
  ->fetchAll()
;
$t->checkEqual($newData, $expected);

$t->start('filterEqual: where size = large AND color = green');
$data = [
  ["id" => "123", "sku" => "MED_BL_DRESS", "size" => "medium", "color" => "black"],
  ["id" => "321", "sku" => "LG_GR_DRESS", "size" => "large", "color" => "green"],
  ["id" => "31321", "sku" => "LG_RD_DRESS", "size" => "large", "color" => "red"]
];
$newData = TableArray::create($data)
  ->filterEqual(['size' => 'large', 'color' => 'green'])
  ->fetchAll()
;
$expected = [
  1 => ["id" => "321", "sku" => "LG_GR_DRESS", "size" => "large", "color" => "green"],
];
$t->checkEqual($newData, $expected);


$t->start('filter Like In');
$data=[
  ['id' => 1, 'art' => '123457', 'feature' => 'TV OLED UHD WLAN 65 Zoll'],
  ['id' => 2, 'art' => '123456', 'feature' => 'TV OLED UHD WLAN 55 Zoll'],
  ['id' => 3, 'art' => '653456', 'feature' => 'TV LED HD 55 Zoll'],
];
$newData = TableArray::create($data)
  ->filterLikeIn('feature','wlan,65')
  ->fetchAll();
$expected=[
  ['id' => 1, 'art' => '123457', 'feature' => 'TV OLED UHD WLAN 65 Zoll'],
  ['id' => 2, 'art' => '123456', 'feature' => 'TV OLED UHD WLAN 55 Zoll'],
]; 
$t->checkEqual($newData, $expected);

$t->start('filter Like In (Integer field)');
$data=[
  ['id' => 1, 'art' => 123457, 'feature' => 'TV OLED UHD WLAN 65 Zoll'],
  ['id' => 2, 'art' => 123456, 'feature' => 'TV OLED UHD WLAN 55 Zoll'],
  ['id' => 3, 'art' => 653456, 'feature' => 'TV LED HD 55 Zoll'],
];
$newData = TableArray::create($data)
  ->filterLikeIn('art','6,7,123456,8')
  ->fetchAll();
$expected=[
  ['id' => 2, 'art' => 123456, 'feature' => 'TV OLED UHD WLAN 55 Zoll'],
]; 
$t->checkEqual($newData, $expected);

$t->start('filter Like All');
$data=[
  ['id' => 1, 'art' => '123457', 'feature' => 'TV OLED UHD WLAN 65 Zoll'],
  ['id' => 2, 'art' => '123456', 'feature' => 'TV OLED UHD WLAN 55 Zoll'],
  ['id' => 3, 'art' => '653456', 'feature' => 'TV LED HD 55 Zoll'],
];
$newData = TableArray::create($data)
  ->filterLikeAll('feature','wlan,65')
  ->fetchAll();
$expected=[
  ['id' => 1, 'art' => '123457', 'feature' => 'TV OLED UHD WLAN 65 Zoll'],
]; 
$t->checkEqual($newData, $expected);

$t->start('filterUnique: remove duplicate rows');
$data=[
  ['id' => 1, 'city' => 'New York', 'street' => 'Fifth Avenue'],
  ['id' => 1, 'city' => 'New York', 'street' => 'Fifth Avenue'],
  ['id' => 2, 'city' => 'New York', 'street' => 'Fifth Avenue'],
  ['id' => 1, 'city' => 'New York', 'street' => 'Fifth Avenue'],
];
$newData = TableArray::create($data)
  ->filterUnique()
  ->fetchAll()
;
$expected=[
  ['id' => 1, 'city' => 'New York', 'street' => 'Fifth Avenue'],
  ['id' => 2, 'city' => 'New York', 'street' => 'Fifth Avenue'],
]; 
$t->checkEqual($newData, $expected);

$t->start('filterUnique: remove dublicate city and street');
$newData = TableArray::create($data)
  ->filterUnique(['city','street'])
  ->fetchAll()
;
$expected=[
  ['id' => 1, 'city' => 'New York', 'street' => 'Fifth Avenue'],
]; 
$t->checkEqual($newData, $expected);

$t->start('filterUnique argument incorrect -> exception');
$closure = function(){
  TableArray::create()->filterUnique(['unknown']);
};
$t->checkException($closure,'InvalidArgumentException');

$t->start('#Aggregate filter MAX: without group');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'MAX'])
  ->fetchAll();
$expected = [//id + group from row with MAX 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$t->checkEqual($newData, $expected);

$t->start('filter MIN without group');
$data = [
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'MIN'])
  ->fetchAll();
$expected = [ //id + group from row with MIN
  ['id' => "3",'group' => 1, 'value' => 1], 
];
$t->checkEqual($newData, $expected);

$t->start('filter SUM without group');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'SUM'])
  ->fetchAll();
$expected = [ //id + group from first row 
  ['id' => "1",'group' => 1, 'value' => 13], 
];
$t->checkEqual($newData, $expected);

$t->start('filter AVG without group');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'AVG'])
  ->fetchAll();
$expected = [ //id + group from first row 
  ['id' => "1",'group' => 1, 'value' => 13/4], 
];
$t->checkEqual($newData, $expected);

$t->start('filter COUNT without group');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'COUNT'])
  ->fetchAll();
$expected = [ //id + group from first row 
  ['id' => "1",'group' => 1, 'value' => 4], 
];
$t->checkEqual($newData, $expected);

$t->start('filter CONCAT without group');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'CONCAT'])
  ->fetchAll();
$expected = [ //id + group from first row 
  ['id' => "1",'group' => 1, 'value' => '2,4,1,6'], 
];
$t->checkEqual($newData, $expected);

$t->start('filter aggregate JSON with 3 groups');
$data = [
  ['id' => 1,'hersteller_id' => "1",'model' => "A3",'farbe' => "schwarz"],
  ['id' => 2,'hersteller_id' => "1",'model' => "A3",'farbe' => "rot"],
  ['id' => 3,'hersteller_id' => "2",'model' => "318",'farbe' => "blau"],
  ['id' => 4,'hersteller_id' => "1",'model' => "A3", 'farbe' => "schwarz"]
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['id' => 'JSON'],['hersteller_id','model','farbe'],",")
  ->fetchAll()
;
$expected = [
  ['id' => '[1,4]','hersteller_id' => "1",'model' => "A3",'farbe' => "schwarz"],
  ['id' => '[2]','hersteller_id' => "1",'model' => "A3",'farbe' => "rot"],
  ['id' => '[3]','hersteller_id' => "2",'model' => "318",'farbe' => "blau"],
];
$t->checkEqual($newData, $expected);

$t->start('filter aggregate in ARRAY with 3 groups');
$data = [
  ['id' => 1,'hersteller_id' => "1",'model' => "A3",'farbe' => "schwarz"],
  ['id' => 2,'hersteller_id' => "1",'model' => "A3",'farbe' => "rot"],
  ['id' => 3,'hersteller_id' => "2",'model' => "318",'farbe' => "blau"],
  ['id' => 4,'hersteller_id' => "1",'model' => "A3", 'farbe' => "schwarz"]
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['id' => 'ARRAY'],['hersteller_id','model','farbe'],",")
  ->fetchAll()
;
$expected = [
  ['id' => [1,4],'hersteller_id' => "1",'model' => "A3",'farbe' => "schwarz"],
  ['id' => [2],'hersteller_id' => "1",'model' => "A3",'farbe' => "rot"],
  ['id' => [3],'hersteller_id' => "2",'model' => "318",'farbe' => "blau"],
];
$t->checkEqual($newData, $expected);

$t->start('filter MIN + MAX without group');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['group' => 'MAX','value' => 'MIN'])
  ->fetchAll();
$expected = [ //id + group from value = MIN 
  ['id' => "3",'group' => 2, 'value' => 1], 
];
$t->checkEqual($newData, $expected);

$t->start('filter MAX: with group');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'max'],['group'])
  ->fetchAll();
$expected = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "4",'group' => 2, 'value' => 6],
];
$t->checkEqual($newData, $expected);

$t->start('filter MAX + SUM: with group');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2, 'value2' => 3], 
  ['id' => "2",'group' => 2, 'value' => 4, 'value2' => 7],
  ['id' => "3",'group' => 1, 'value' => 1, 'value2' => 2], 
  ['id' => "4",'group' => 2, 'value' => 6, 'value2' => 8],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'MAX', 'value2' => 'AVG'],['group'])
  ->fetchAll();
$expected = [ 
  ['id' => "1",'group' => 1, 'value' => 2, 'value2' => 2.5], 
  ['id' => "4",'group' => 2, 'value' => 6, 'value2' => 7.5],
];
$t->checkEqual($newData, $expected);

$t->start('filter Max: with 2 groups');
$data = [ 
  ['id' => "1",'group' => 1, 'group2' => 'A', 'value' => 2], 
  ['id' => "2",'group' => 2, 'group2' => 'A', 'value' => 4],
  ['id' => "3",'group' => 1, 'group2' => 'A', 'value' => 1], 
  ['id' => "4",'group' => 2, 'group2' => 'A', 'value' => 6],
  ['id' => "5",'group' => 1, 'group2' => 'B', 'value' => 1], 
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'max'],['group','group2'])
  ->fetchAll();
$expected = [ 
  ['id' => "1",'group' => 1, 'group2' => 'A', 'value' => 2], 
  ['id' => "4",'group' => 2, 'group2' => 'A', 'value' => 6],
  ['id' => "5",'group' => 1, 'group2' => 'B', 'value' => 1], 
];
$t->checkEqual($newData, $expected);

$t->start('group_max argument incorrect -> exception');
$closure = function(){
  TableArray::create([[]])
    ->filterGroupAggregate(['value' => 'max'],['group11'])
  ;
};
$t->checkException($closure,'InvalidArgumentException');

$t->start('filterGroup Count');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 1, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'COUNT'],['group'])
  ->fetchAll();
$expected = [ 
  ['id' => "1",'group' => 1, 'value' => 3],
  ['id' => "2",'group' => 2, 'value' => 1],
];
$t->checkEqual($newData, $expected);

$t->start('filterGroup Concat');
$data = [ 
  ['id' => "1",'group' => 1, 'value' => 2], 
  ['id' => "2",'group' => 2, 'value' => 4],
  ['id' => "3",'group' => 1, 'value' => 1], 
  ['id' => "4",'group' => 1, 'value' => 6],
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['value' => 'CONCAT'],['group'],",")
  ->fetchAll();
$expected = [ 
  ['id' => "1",'group' => 1, 'value' => '2,1,6'],
  ['id' => "2",'group' => 2, 'value' => '4'],
];
$t->checkEqual($newData, $expected);

$t->start('filterGroup Concat with 2 groups and order');
$data = [ 
  ['id' => "1",'firstname' => 'Fritz', 'surname' => "Schulz", 'tel' => '08505678'], 
  ['id' => "2",'firstname' => 'Anna', 'surname' => "Schulz", 'tel' => '08505678'], 
  ['id' => "3",'firstname' => 'Fritz', 'surname' => "Schulz", 'tel' => '0986644'], 
  ['id' => "4",'firstname' => 'Fritz', 'surname' => "Meier", 'tel' => '0338505678'], 
];
$newData = TableArray::create($data)
  ->filterGroupAggregate(['tel' => 'CONCAT'],['surname','firstname'],",")
  ->orderBy("surname,firstname")
  ->fetchAll()
;
$expected = [ 
  ['id' => "4",'firstname' => "Fritz",'surname' => "Meier",'tel' => "0338505678"],
  ['id' => "2",'firstname' => "Anna",'surname' => "Schulz",'tel' => "08505678"],
  ['id' => "1",'firstname' => "Fritz",'surname' => "Schulz", 'tel' => "08505678,0986644"],
];
$t->checkEqual($newData, $expected);

$t->start('filter: remove all rows with a null value');
$data=[ 
  ['id' => 3, 'art' => null, 'v' => 9],  
  ['id' => 4, 'art' => 56, 'v' => 2 ], 
  
];
$newData = TableArray::create($data)
  ->filter()
  ->fetchAll();
$expected=[
  ['id' => 4, 'art' => 56, 'v' => 2 ], 
];
$t->checkEqual($newData, $expected); 

$t->start('filter function: all rows with art * V > 100');
$data=[ 
  ['id' => 1, 'art' => 12, 'v' => 7], 
  ['id' => 2, 'art' => 56, 'v' => 2 ],
  ['id' => 3, 'art' => null, 'v' => 9],  
  ['id' => 4, 'art' => 34, 'v' => 2 ], 
];
$newData = TableArray::create($data)
  ->filter(function($row){return ($row['art'] * $row['v']) > 100;})
  ->fetchAll();
$expected=[
  ['id' => 2, 'art' => 56, 'v' => 2 ], 
];
$t->checkEqual($newData, $expected);

//walk Version > 1.7
$t->start('walk: modify rows');
$data=[ 
  ['id' => 1, 'art' => 12, 'v' => 7], 
  ['id' => 2, 'art' => 56, 'v' => 2 ],
];

$callback = function($row, $key, $userData){
  $row['v'] += $userData;
  return $row;
};
$userData = 3;

$newData = TableArray::create($data)
  ->walk($callback, $userData)
  ->fetchAll()
;
$expected = [
  ['id' => 1, 'art' => 12, 'v' => 10], 
  ['id' => 2, 'art' => 56, 'v' => 5 ],
];
$t->checkEqual($newData, $expected);

//transpose Version >=1.73
$t->start('transpose array');
$data=['id' => [1,2], 'art' => [12,56], 'v' => [7,2]];

$newData = TableArray::create($data)
->transpose()
->fetchAll()
;

$expected = [
  ['id' => 1, 'art' => 12, 'v' => 7], 
  ['id' => 2, 'art' => 56, 'v' => 2 ],
];
$t->checkEqual($newData, $expected);

$t->start('addFlatKeys: add flatten cols from array-fields');
$data = [
  ['id' => 1, '@attributes' => ['currency' => "USD",'rate' => "1.1370"]],
  ['id' => 2, '@attributes' => ['currency' => "JPY",'rate' => "1.28"]],
];
$newData = TableArray::create($data)
  ->addFlatKeys()
  ->fetchAll();
$expected = [
  ['id' => 1, '@attributes' => ['currency' => "USD",'rate' => "1.1370"],
    '@attributes.currency' => "USD",'@attributes.rate' => "1.1370"],
  ['id' => 2, '@attributes' => ['currency' => "JPY",'rate' => "1.28"],
    '@attributes.currency' => "JPY",'@attributes.rate' => "1.28"],
];
$t->checkEqual($newData, $expected);  

$t->start('addFlatKeys: sort by @attributes->currency');
$data = [
  ['id' => 1, '@attributes' => ['currency' => "USD",'rate' => "1.1370"]],
  ['id' => 2, '@attributes' => ['currency' => "JPY",'rate' => "1.28"]],
];
$newData = TableArray::create($data)
  ->addFlatKeys()
  ->orderBy('@attributes.currency')
  ->select('id,@attributes') 
  ->fetchAll();
$expected = [
  ['id' => 2, '@attributes' => ['currency' => "JPY",'rate' => "1.28"]],
  ['id' => 1, '@attributes' => ['currency' => "USD",'rate' => "1.1370"]],
];
$t->checkEqual($newData, $expected);

//check if is a table-Array
$t->start('check if data is a table-Array');
$data = [ //is a table array
  [1,2,3],
  [4,5,6]
];  
$result = TableArray::check($data);
$t->checkEqual($result, true); 

$t->start('check if data is a table-Array');
$data = [ //is not a table array
  [1,2,3],
  [4,5]
];  
$result = TableArray::check($data);
$t->checkEqual($result, false); 

$t->start('check if data is a table-Array');
$data = [1,2,3]; //not a table array
$result = TableArray::check($data);
$t->checkEqual($result, false); 

$t->start('check if data is a table-Array');
$data=[ //is a table array
  ['id' => 1, 'v' => 7], 
  ['id' => 2, 'v' => 2 ],
];
$result = TableArray::check($data);
$t->checkEqual($result, true); 

$t->start('check if data is a table-Array');
$data=[ //is not a table array
  ['id' => 1, 'v' => 7], 
  ['id' => 2, 'X' => 2 ],  //different keys
];
$result = TableArray::check($data);
$t->checkEqual($result, false);

//allRowKeys
$t->start('get Keys from all rows');
$data=[ //is not a table array
  ['id' => 1, 'v' => 7], 
  ['id' => 2, 'X' => 2 ],  //different keys
];
$result = TableArray::allRowKeys($data);
$expected = ['id','v','X'];
$t->checkEqual($result, $expected);

$t->start('get Keys from all rows');
$data = [
  [1,2,3], 
  [11, "a" => "val_a"],
'c' =>  [12,'b' => 'val_b', 'a' => 'val2_a'],
  [2 => 22]
];
$result = TableArray::allRowKeys($data);
$expected = [0,1,2,'a','b'];
$t->checkEqual($result, $expected);

$t->start('get Keys from 1 dim array -> false');
$data = [1,2,3];
$result = TableArray::allRowKeys($data);
$t->checkEqual($result, false);

//rectify to table structure
$t->start('rectify to table structure');
$data = [
  [1,2,3], 
  [11, "a" => "val_a"],
'c' =>  [12,'b' => 'val_b', 'a' => 'val2_a'],
  [2 => 22]
];
$result = TableArray::create($data)
  ->rectify()
  ->fetchAll()
;
$expected = [
        [ 0 => 1, 1 => 2, 2 => 3, 'a' => NULL, 'b' => NULL],
        [ 0 => 11, 1 => NULL, 2 => NULL, 'a' => "val_a", 'b' => NULL],
'c' =>  [ 0 => 12, 1 => NULL, 2 => NULL, 'a' => "val2_a", 'b' => "val_b"],
        [ 0 => NULL, 1 => NULL, 2 => 22, 'a' => NULL, 'b' => NULL]
];
$t->checkEqual($result, $expected);

/*
 * debugging
 */
$t->setResultFilter("html");
$t->startOutput('debug print');
$data = [ //is a table array
  [11,12],
  [21,22]
];  
TableArray::create($data)
  ->dprint('default limit 100')
;
$t->checkOutput('default limit 100,11,22');

$t->startOutput('debug print limit 1');
$data = [ //is a table array
  [11,12],
  [21,22]
];  
TableArray::create($data)
  ->dprint('limit 1',1)
;
$t->checkOutput('limit 1,11,12');
$t->setResultFilter();

/*
 * performance
 */
$t->start('create big Integer field');
//create data
$data =[];
//$len = 1000;
for($i=1; $i <= $len ; $i++){
  $data[] = ['id' => $i, 'no' => $i+10];
}
$t->checkEqual(count($data), $len);

$t->start('like in big Integer field');
$newData = TableArray::create($data)
  ->filterLikeIn('no','6,7,356,956')
  ->fetchAll();
$expected=[
  ['id' => 346, 'no' => 356],
  ['id' => 946, 'no' => 956],
];
$t->checkEqual($newData, $expected);

$t->start('create SQLite Table');
$db = new PDO(
  "sqlite::memory:",
  NULL,
  NULL,
  [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$sql = "CREATE TABLE tab (id INTEGER PRIMARY KEY  NOT NULL , no INTEGER)";
$r = $db ->exec($sql);
$sql = "INSERT INTO tab (id, no) VALUES ";
for($i=1; $i <= $len; $i++){
 $sql .= "(".$i.",".($i+10)."),";
}
$sql = rtrim($sql,",");
$count = $db->exec($sql);
$t->checkEqual($count, $len);

$t->start('select SQLite Table');
$sql = "SELECT * from tab WHERE no IN(6,7,356,956)";
$stmt = $db->query($sql);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
$expected=[
  ['id' => '346', 'no' => '356'],
  ['id' => '946', 'no' => '956'],
];
$t->checkEqual($result, $expected);

$t->start('select SQLite Table and FETCH_KEY_PAIR');
$sql = "SELECT * from tab WHERE no IN(6,7,356,956)";
$stmt = $db->query($sql);
$result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$expected=[ 346 => '356', 946 => '956'];
$t->checkEqual($result, $expected);

$t->start('Create from PDO Statement');
$sql = "SELECT * from tab WHERE no IN(6,7,356,956)";
$stmt = $db->query($sql);
$tabArr = TableArray::create($stmt);
$result = $tabArr->fetchAll();
$expected=[
  ['id' => '346', 'no' => '356'],
  ['id' => '946', 'no' => '956'],
];
$t->checkEqual($result, $expected);

$t->start("Multiple fetch");
$result = $tabArr->fetchKeyValue('id','no');
$expected=[ 346 => '356', 946 => '956'];
$t->checkEqual($result, $expected);

//
$t->start('create Testdata (20.000 rows)');
$data =[];
$id = 1;
$group2 = 'A';
for( $dt=date_create('today - 70 days'); $dt < date_create('today'); $dt->modify('+15 Minutes')){
   $strDate = $dt->format("Y-m-d H:i:s");
   $data[] = ['id' => $id++, 'date' => $strDate, 'group' => 1, 'group2' => $group2];
   $data[] = ['id' => $id++, 'date' => $strDate, 'group' => 2, 'group2' => $group2];
   $data[] = ['id' => $id++, 'date' => $strDate, 'group' => 3, 'group2' => $group2];
   $group2 = ($group2 == 'A') ? 'B' : 'A';
}
$count = count($data);
$t->check($count, $count > 0);
  
$t->start('group max'); 
$newData = TableArray::create($data)
  ->filterGroupAggregate(['date' => 'MAX'],['group','group2'])
  ->fetchAll();
$t->check($newData, count($newData) == 6); 

//countable interface Version 1.71
$t->start('countable object TableArray');
$data=[ //is a table array
  ['id' => 1, 'v' => 7], 
  ['id' => 2, 'v' => 2 ],
];
$result = count(new TableArray($data));
$t->checkEqual($result, 2);

$t->start('check function ungroup');
$groupedArray = [
  "A" => [
    ['id' => 1, 'val' => 21],
    ['id' => 3, 'val' => 32],
  ],
  "B" => [
    ['id' => 2, 'val' => 28],
    ['id' => 4, 'val' => 44],
  ],
];
$data = TableArray::unGroup($groupedArray,['type']);
$expected = [
  ['type' => "A", 'id' => 1, 'val' => 21],
  ['type' => "A", 'id' => 3, 'val' => 32],
  ['type' => "B", 'id' => 2, 'val' => 28],
  ['type' => "B", 'id' => 4, 'val' => 44],
];
$t->checkEqual($data, $expected);

$t->start('check wildcardMatch with ?');
/*
 * checks for internal methods and functions
 */
$string = 'a.b1.b2.c';
$pattern = 'a.?.?.c';
$match = TableArray::wildcardMatch($pattern, $string);
$t->checkEqual($match,true);

$t->start('check wildcardMatch with ?');
$string = 'a.b1.b2.c';
$pattern = 'a.b.?.c';
$match = TableArray::wildcardMatch($pattern, $string);
$t->checkEqual($match,false);

$t->start('check wildcardMatch with ?');
$string = 'a.bbx.b2.c';
$pattern = 'a.b?.?.c';
$match = TableArray::wildcardMatch($pattern, $string);
$t->checkEqual($match,true);

$t->start('check wildcardMatch with * and ?');
$string = 'a.b1.b2.b3.c.d';
$pattern = 'a.*.c.?';  
$match = TableArray::wildcardMatch($pattern, $string);
$t->checkEqual($match,true);

$t->start('check wildcardMatch with * and ?');
$string = 'a.b1.b2.b3.c.d';
$pattern = 'a.*.d.?';  
$match = TableArray::wildcardMatch($pattern, $string);
$t->checkEqual($match,false);
  
//Ausgabe 
echo $t->getHtml();
