<?php
/**
.---------------------------------------------------------------------------.
|  Software: Function Collection for Table-Arrays                           |
|  Version: 1.76                                                            |
|  Date: 2020-01-01                                                         |
|  PHPVersion >= 5.6                                                        |
| ------------------------------------------------------------------------- |
| Copyright © 2018 Peter Junk (alias jspit). All Rights Reserved.           |
| ------------------------------------------------------------------------- |
|   License: Distributed under the Lesser General Public License (LGPL)     |
|            http://www.gnu.org/copyleft/lesser.html                        |
| This program is distributed in the hope that it will be useful - WITHOUT  |
| ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or     |
| FITNESS FOR A PARTICULAR PURPOSE.                                         |
'---------------------------------------------------------------------------'
*/
class tableArray extends \ArrayIterator implements \JsonSerializable, \Countable
{
  private $userFct = [];
  private $sqlSort = [];  //internal
  private $selectKeys = null;  //array with valid keys after SELECT, default null = All

  private $data = [];  //2.dim 
  
  const CHECK_DATA_DURING_CONSTRUCT = false;
  const SEPARATOR = "\x02";
  
 
 /*
  * @param mixed : table array or iterator
  */
  public function __construct($data = [[]], array $keyPathToData = null){
    if(is_array($data)){
      $this->data = $data;
    }
    elseif($data instanceof tableArray){
      $this->data = $data->fetchAll();
    }
    //iterable?
    elseif($data instanceof \Traversable){
      $this->data = iterator_to_array($data);
    }
    else{
      $msg = "Parameter for ".__METHOD__." must be a array or iterable";
      throw new \InvalidArgumentException($msg);
    }
    //optional parameter 2 : array with keys to table-array
    if($keyPathToData !== null){
      foreach($keyPathToData as $key){
        if(array_key_exists($key, $this->data)) {
          $this->data = $this->data[$key];
        }
        else {
          $msg = "Parameter 2 for ".__METHOD__." must be a array with valid keys";
          throw new \InvalidArgumentException($msg);
        }  
      }      
    }
    
    $firstRow = reset($this->data);
    if(is_object($firstRow)){
      $firstRow = (array)$firstRow;
      foreach($this->data as $i => $row){
        $this->data[$i] = (array)$row;
      }
    }
    //
    $this->rectify();
    
    if(self::CHECK_DATA_DURING_CONSTRUCT) {
      if(!self::check($this->data)) {
        $msg = "Parameter must be a table-array for ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }      
    }
    elseif(!is_array($firstRow)) {
      $msg = "Parameter must a array with dimension 2 ".__METHOD__;
      throw new \InvalidArgumentException($msg);
    }
        
    mb_internal_encoding("UTF-8");
    $this->userFct = [
      'UPPER' => 'strtoupper',  //
      'LOWER' => 'strtolower',
      'FORMAT' => 'sprintf',  //par: 'format',field,[field]
      'DATEFORMAT' => function($format,$date){
        if(is_string($date)) $date = date_create($date);
        if($date instanceOf DateTime) {
          return $date->format($format);
        }
        return "?"; 
      },
      'REPLACE' => 'str_replace',  //par: 'search','replace',fieldname
      'SUBSTR' => 'mb_substr',  //par: fieldname,'start',['length']
      'LIKE' => function($val,$likePattern){  //case insensitiv
        $pattern = preg_quote($likePattern,"~");
        $pattern = strtr($pattern, ['%' => '.*?', '_' => '.']);
        return preg_match('~^'.$pattern.'$~i',$val);
      },
      'INTVAL' => 'intval',
      'FLOATVAL' => 'floatval',
      'TRIM' => 'trim',  //par: fieldName[,'$character_mask']
      'SCALE' => function($val, $factor = 1, $add = 0, $format = null){
        $val = $val * $factor + $add;
        if(is_string($format)) {
          $val = sprintf($format, $val);
        }
        return $val;  
      },
      'NULLCOUNT' => function(){
        $sum = 0;
        foreach(func_get_args() as $arg){
          $sum += (int)($arg === NULL);
        }
        return $sum;
      },
    ];
  }

 /*
  * create a instance
  * @param $data : 2 dim array, iterator or tableArray Instance
  * @return instance of tableArray
  */
  public static function create($data = [[]],$keyPathToData = []){
    return new static($data, $keyPathToData);
  }

 /*
  * create a instance from JSON-String
  * @param $jsonStr : represents a 2-dimensional array
  * @return instance of tableArray
  */
  public static function createFromJson($jsonStr, $keyPathToData = []){
    return new static(json_decode($jsonStr, true),$keyPathToData);
  }

 /*
  * create a instance from XML
  * @param $xml: xml-String or SimpleXML Object 
  * @return instance of tableArray
  */
  public static function createFromXML($xml){
    if(is_string($xml)) {
      $xml = simplexml_load_string($xml);
    }
    if(!is_object($xml)) {
      $msg = "Parameter must be a valid XML for ".__METHOD__;
      throw new \InvalidArgumentException($msg);
    }
    //create a array
    $array = [];
    foreach($xml as $element) {
      
      $array[] = json_decode(str_replace("{}",'""',json_encode($element)), true);
    }
    return new static($array);
  }
  
 /*
  * create from a numerical 1 dimensional array
  * @param $array 1 dim array
  * @param $delimiter delimiter for split rows
  *   delimter one cher, handle as csv
  *   delimiter 2-4 chars, handle with explode
  *   delimter >= 5 chars use as regular expression
  */
  public static function createFromOneDimArray(array $array, $delimiter = ""){
    $data = [];
    $lenDelim = strlen($delimiter);
    $isDelimRegEx = $lenDelim >= 5 && @preg_match($delimiter, null) !== false;
    $regExWithNamedGroups = $isDelimRegEx && preg_match("/\(\?P?[<']/",$delimiter) == 1;
    foreach($array as $key => $value){
      if($lenDelim == 0){
        $data[] = [$key, $value];
      }
      elseif($lenDelim == 1) {
        $data[] = str_getcsv($value, $delimiter);
      }
      elseif($lenDelim < 5) {
        $data[] = explode($delimiter, $value);
      }
      elseif($isDelimRegEx){
        //handle regex
        if(preg_match($delimiter, $value, $match)){
          $countMatch = count($match);
          if($countMatch == 1) $row = [$key,$match[0]];
          elseif($countMatch == 2) $row = [$key,$match[1]];
          elseif($regExWithNamedGroups) {
            $row = array_filter($match,'is_string',ARRAY_FILTER_USE_KEY);
          }
          else $row = array_slice($match,1);
        }
        else {
          //error
          $msg = "Wrong 2.Parameter '".$delimiter."' for ".__METHOD__;
          throw new \InvalidArgumentException($msg);
        }
        $data[] = $row;        
      }
      else {
        //error
        $msg = "Wrong 2.Parameter '".$delimiter."' for ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }      
    }
    return new static($data);    
  }
  
 /*
  * create from string how get from file
  * @param string $input
  * @param string $regExRow for split rows
  * @param string $regExSplitLines for split lines
  */
  public static function createFromString($input, $regExRow = ',', $regExSplitLines = '/\R/' ){
    $arr = @preg_split($regExSplitLines,$input,NULL,PREG_SPLIT_NO_EMPTY);
    if(!is_array($arr)) $arr = explode($regExSplitLines); 
    if(!is_array($arr)) {
      //error
      $msg = "Wrong 3.Parameter '".$regExSplitLines."' for ".__METHOD__;
      throw new \InvalidArgumentException($msg);
    }
    return self::createFromOneDimArray($arr, $regExRow);
  }

 /*
  * check if data is a array with table-structure
  * @param $data : array 
  * @return true ok or false
  */
  public static function check(array $data){
    $keys = null;
    foreach($data as $row){
      if(is_object($row)) $row = (array)$row;
      if(!is_array($row)) return false;
      $curKeys = array_keys($row);
      if($keys === null) $keys = $curKeys;
      elseif($curKeys != $keys) return false;
    }
    return true;
  }

 /*
  * add a userfuction (closure)
  * @param string $name
  * @param string $function : closure
  * @return $this
  */
  public function addSqlFunction($name, $function){
    $this->userFct[$name] = $function;
    return $this;
  }

  public function addSqlFunctionFromArray(array $functions){
    $this->userFct = array_merge($this->userFct, $functions);
    return $this;
  }
  
 /*
  * get a userfunction
  * @param string $name
  * @return closure or false if error
  */
  public function getSqlFunction($name){
    return isset($this->userFct[$name])
      ? $this->userFct[$name]
      : false;
  }
 
 /*
  * sort with uasort
  * @param string $sqlOrderTerm: a string how for SQL OrderBy 
  * @return $this
  */  
  public function orderBy($sqlOrderTerm){
    $this->sqlSort = $this->setSort($sqlOrderTerm);
    //uasort($this->data,array($this,"sortFunction"));
    usort($this->data,array($this,"sortFunction"));
    return $this;
  }

 /*
  * set select
  * @param string or array
  * @return $this
  */  
  public function select($colKeys){
    if(is_array($colKeys)) $colKeys = implode(",", $colKeys);

    if(!is_string($colKeys)) {
      $msg = "Parameter must array or string ".__METHOD__;
      throw new \InvalidArgumentException($msg);
    }
    if($colKeys == "*") {
      $this->selectKeys = null;
      return $this;
    }
    //validate
    if(strpbrk($colKeys,";|+<>=*/") !== false){
      $msg = "forbidden char in '$key' ".__METHOD__;
      throw new \InvalidArgumentException($msg);
    }
    //prepare and explode terms
    //$validFieldNames = array_keys(reset($this->data));
    $firstDataRow = reset($this->data);
    $selectFileds = [];
    foreach($this->splitarg($colKeys) as $termObj){
      //termObj with ->name, ->as, ->fct, ->fpar, ->term
      if($fct = $termObj->fct) {
        //function call
        if(!array_key_exists($fct,$this->userFct)){
          $msg = "Unknown Function  '".$fct."' ".__METHOD__;
          throw new \InvalidArgumentException($msg);
        }
        $parObjects = $this->splitarg($termObj->fpar);
        //check if fields ok and collect in a array
        $parameters = [];
        foreach($parObjects as $parObj){
          $trimStr = trim($parObj->term,'\'"');
          if($parObj->term == $trimStr AND !array_key_exists($trimStr,$firstDataRow)){
            $msg = "Unknown Parameter-Fieldname '$trimStr' ".__METHOD__;
            throw new \InvalidArgumentException($msg);
          }
          $parameters[] = $parObj->term;
        }
        
        $nameAs = $termObj->as;
        foreach($this->data as $keyData => $row){
          //current parameters
          $curPar = [];
          foreach($parameters as $par){
            $trimStr = trim($par,'\'"');
            $curPar[] = $trimStr == $par ? $row[$par] : $trimStr;
          }
          
          $this->data[$keyData][$nameAs] = call_user_func_array(
            $this->userFct[$fct], 
            $curPar
          );
        }
        $selectFileds[] = $nameAs;
        $validFieldNames[] = $nameAs; 
      }
      else {
        if(array_key_exists($termObj->name,$firstDataRow)) {
          $fieldName = $termObj->name;
          if($nameAs = $termObj->as){
            foreach($this->data as $keyData => $row){
              $this->data[$keyData][$nameAs] = $row[$fieldName]; 
            }
            $selectFileds[] = $nameAs;
            $validFieldNames[] = $nameAs; 
          }
          else {
            $selectFileds[] = $fieldName;
          }
        }
        else {
          $msg = "Unknown fieldname '$termObj->name' ".__METHOD__;
          throw new \InvalidArgumentException($msg);
        }  
      }
    }
    $this->selectKeys = $selectFileds;
    return $this;
  }
 
 /*
  * filter all rows with field is like all elements from array
  * @param $fieldName: key from a column
  * @param $inList : List of like-Terms
  * @return $this
  */  
  public function filterLikeAll($fieldName, $inList){
    return $this->filterLike($fieldName, $inList, true);
  }

  
 /*
  * filter all rows with field is like any element from array
  * @param $fieldName: key from a column
  * @param $inList : List of like-Terms
  * @return $this
  */  
  public function filterLikeIn($fieldName, $inList){
    return $this->filterLike($fieldName, $inList, false);
  }

 /*
  * filter all rows with field is unique from array
  * @param $fieldNames: array with fieldNames or null for all
  * @return $this
  */  
  public function filterUnique(array $fieldNames = null){
    if($fieldNames === null){
      //all fields
      foreach($this->data as $key => $row){
        $keyFound = array_search($row,$this->data);
        if($keyFound != $key) {
          unset($this->data[$key]);
        }
      }
    }
    else {
      $invalidFieldName = $this->invalidFieldNames($fieldNames);
      if($invalidFieldName){
        $msg = "Unknown fieldname '$invalidFieldName' ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }
      $filterCols = [];
      $flipFields = array_flip($fieldNames);
      foreach($this->data as $key => $row){
        $fieldsFromRow = array_intersect_key($row,$flipFields);
        if(in_array($fieldsFromRow, $filterCols)) {
          unset($this->data[$key]);
        }
        else {
          $filterCols[] = $fieldsFromRow;
        }
      }
    }
    $this->data = array_values($this->data);
    return $this;
  }

 /*
  * filterGroupMax 
  * @param $maxFieldName: key from column for search Maximum
  * @param $groups: array with fieldnames for groups
  * @return $this
  */  
  public function filterGroupMax($maxFieldName, array $groups = []){
    //check if fieldNames valid
    $firsRow = reset($this->data);
    $fields = $groups;
    $fields[] = $maxFieldName;
    foreach($fields as $fieldName){
      if(!array_key_exists($fieldName, $firsRow)){
        $msg = "Unknown fieldname '$fieldName' ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }
    }
    $newData = [];
    foreach($this->data as $dKey => $row){
      //create groupkey
      $groupkey = "";
      foreach($groups as $key) {
        if($groupkey !== "") $groupkey .= self::SEPARATOR;       
        $groupkey .= $row[$key];
      }
      if(isset($newData[$groupkey]) 
        AND $newData[$groupkey][$maxFieldName] >= $row[$maxFieldName]){
          continue;
      }
      $newData[$groupkey] = $row; 
    }
    $this->data = array_values($newData);
    return $this;
  }

 /*
  * filterGroupMin 
  * @param $minFieldName: key from column for search Minimum
  * @param $groups: array with fieldnames for groups
  * @return $this
  */  
  public function filterGroupMin($minFieldName, array $groups = []){
    //check if fieldNames valid
    $firsRow = reset($this->data);
    $fields = $groups;
    $fields[] = $minFieldName;
    foreach($fields as $fieldName){
      if(!array_key_exists($fieldName, $firsRow)){
        $msg = "Unknown fieldname '$fieldName' ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }
    }
    $newData = [];
    foreach($this->data as $dKey => $row){
      //create groupkey
      $groupkey = "";
      foreach($groups as $key) {
        if($groupkey !== "") $groupkey .= self::SEPARATOR;       
        $groupkey .= $row[$key];
      }
      if(isset($newData[$groupkey]) 
        AND $newData[$groupkey][$minFieldName] <= $row[$minFieldName]){
          continue;
      }
      $newData[$groupkey] = $row; 
    }
    $this->data = array_values($newData);
    return $this;
  }

 /*
  * filterGroupSum 
  * @param $sumFieldName: key from column for Summe
  * @param $groups: array with fieldnames for groups
  * @return $this
  */  
  public function filterGroupSum($sumFieldName, array $groups = []){
    //check if fieldNames valid
    $firsRow = reset($this->data);
    $fields = $groups;
    $fields[] = $sumFieldName;
    foreach($fields as $fieldName){
      if(!array_key_exists($fieldName, $firsRow)){
        $msg = "Unknown fieldname '$fieldName' ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }
    }
    $newData = [];
    foreach($this->data as $dKey => $row){
      //create groupkey
      $groupkey = "";
      foreach($groups as $key) {
        if($groupkey !== "") $groupkey .= self::SEPARATOR;       
        $groupkey .= $row[$key];
      }
      if(!isset($newData[$groupkey])) {
        $newData[$groupkey] = $row;
        $newData[$groupkey][$sumFieldName] = 0;
      }
      if(is_numeric($row[$sumFieldName])) {
        $newData[$groupkey][$sumFieldName] += $row[$sumFieldName];
      }
    }
    $this->data = array_values($newData);
    return $this;
  }
  
  
 /*
  * filter all rows if $callback returns true
  * @param $callback: userfunction with parameter $row
  *   if returnvalue from $callback is false current row will delete
  * if $callback == null: remove all rows with a null value 
  * @return $this
  */  
  public function filter($callback = null){
    if(empty($this->data)) return $this;
    foreach($this->data as $key => $row){
      if($callback === null){
        if(!in_array(null,$row)) continue;
      }
      else {
        if($callback($row)) continue;
      }
      unset($this->data[$key]);      
    }
    $this->data = array_values($this->data);
    return $this;    
  }

 /*
  * walk over all rows
  * @param $callback: userfunction with parameter $row, $key, $userParam
  *   if returnvalue from $callback is false current row will delete
  * if $callback == null: remove all rows with a null value 
  * @return $this
  */  
  public function walk($callback, $userParam = null){
    if(empty($this->data)) return $this;
    foreach($this->data as $key => $row){
      $newRow = $callback($row, $key, $userParam);
      $this->data[$key] = $newRow;
    }
    return $this;    
  }

  /*
  * transposes the array (switch rows and columns)
  * @return $this
  */  
  public function transpose(){
    $transArr = [];
    foreach($this->data as $keyRow => $subArr) {
      foreach($subArr as $keyCol => $value) {
        $transArr[$keyCol][$keyRow] = $value;
      }
    }
    $this->data = $transArr;
    return $this;
  }
 
  
 /*
  * inner Join On
  * @param $ref array Reference
  * @param $idRef name id for ON from Reference-Array
  * @param $refId name id Basis-Array
  * @return $this
  */
  public function innerJoinOn($ref, $tableAlias, $idRef, $refId){
    return $this->joinOn($ref, $tableAlias, $idRef, $refId, 'inner');
  }

 /*
  * left Join On
  * @param $ref array Reference
  * @param $idRef name id for ON from Reference-Array
  * @param $refId name id Basis-Array
  * @return $this
  */
  public function leftJoinOn($ref, $tableAlias, $idRef, $refId){
    return $this->joinOn($ref, $tableAlias, $idRef, $refId, 'left');
  }
  
  
 /*
  * convert to pivot table
  * @param $group Field name for grouping
  * @param $pivot Field name for pivot
  * @param $case Field name for case
  * @return $this
  */  
  public function pivot($group, $pivot, $case){
    $this->data = $this->fetchAll();
    $pivKeys = [];
    foreach($this->data as $row){
      $pivKeys[] = $pivot.'.'.$row[$case];
    }
    $piv = [];
    foreach($pivKeys as $key){
      $piv[$key] = null; 
    }
    $newData = [];
    foreach($this->data as $row){
      $newData[$row[$group]][$group] = $row[$group];
      $pivKey = $pivot.'.'.$row[$case];
      $newData[$row[$group]][$pivKey] = $row[$pivot];
    }
    foreach($newData as $key => $row){
      $newData[$key] += $piv; 
    }
    $this->data = $newData;
    return $this;
  }
 
 /*
  * delete all rows < number
  * @param integer number
  * @return $this
  */  
  public function offset($number){
    $i = 0;
    foreach($this->data as $key => $row){
      if($i >= $number) break;
      unset($this->data[$key]);
      $i++;
    }
    $this->data = array_values($this->data);
    return $this;
  }

 /*
  * delete all rows > number
  * @param integer number
  * for number <0 count from end
  * @return $this
  */  
  public function limit($number){
    if($number >= 0){
      $i = 0;
      foreach($this->data as $key => $row){
        $i++;
        if($i <= $number) continue;
        unset($this->data[$key]);
      }
      $this->data = array_values($this->data);
      return $this;
    }
    else {
      return $this->offset($this->count()+$number);
    }
  }
  
 /*
  * add Keys from data as new column
  * @param string new Field Name 
  * @return $this
  */
  public function addKeys($newFieldName = "_key"){
    foreach($this->data as $key => $row){
      $this->data[$key][$newFieldName] = $key;
    }
    return $this;    
  }

  /*
  * use a column as key for row
  * the column must contain unique values for new keys
  * @param string $fieldName
  * @return $this
  */
  public function fieldAsKey($fieldName = "_key"){
    if(!array_key_exists($fieldName, reset($this->data))){
      //error
      $msg = "Unknown Field-Name '".$fieldName."' ".__METHOD__;
      throw new \InvalidArgumentException($msg);
    }
    foreach($this->data as $key => $row){
      $newKey = $row[$fieldName];
      unset($this->data[$key]);
      if(array_key_exists($newKey, $this->data)) continue;
      unset($row[$fieldName]);
      $this->data[$newKey] = $row;
    }
    return $this;    
  }
 

 /*
  * flatten: flat all fields from row 
  */
  public function flatten($delimter = "."){
    if(array_filter(reset($this->data),function($val){ return !is_scalar($val);})) {
      foreach($this->data as $i => $row){
        $this->data[$i] = $this->arrayFlatten($row,$delimter);
      }
    }
    return $this;
  }

 /*
  * addFlatKeys: add flat cols from array-fields 
  */
  public function addFlatKeys($delimter = "."){
    if(array_filter(reset($this->data),function($val){ return !is_scalar($val);})) {
      foreach($this->data as $i => $row){
        $this->data[$i] = array_merge($row,$this->arrayFlatten($row,$delimter));
      }
    }
    return $this;
  }
 
 /*
  * get the array
  * @param: integer $limit > 0
  * @return array
  */  
  public function fetchLimit($limit = 1) {
    $data = [];
    foreach($this->data as $row){
      if($limit-- <= 0) break;
      $data[] = $row;
    }
    return $this->getSelectData($data);
  }

  /*
  * get limit elements from end
  * @param: integer $limit > 0
  * @return array
  */  
  public function fetchLimitFromEnd($limit = 1) {
    $start = $this->count()-$limit;
    $i = 0;
    $data = [];
    foreach($this->data as $row){
      if($i++ < $start) continue;
      $data[] = $row;
    }
    return $this->getSelectData($data);
  }
 

 /*
  * get the array
  * @return array
  */  
  public function fetchAll(){
    return $this->getSelectData($this->data);
  }

 /*
  * get array of stdClass-Objects
  * @return array
  */  
  public function fetchAllObj(){
    return array_map(function($v){return (object)$v;}, $this->fetchAll());
  }

 /*
  * get array as JSON-String
  * @return string
  */  
  public function fetchAllAsJson($jsonOptions = 0){
    return json_encode($this->fetchAll(), $jsonOptions);
  }

 /*
  * get a array(key => Value)
  * @return array
  */  
  public function fetchKeyValue($fieldNameKey, $fieldNameValue){
    //ignore select
    $firstDataRow = reset($this->data);
    if(array_key_exists($fieldNameKey, $firstDataRow) AND
      array_key_exists($fieldNameValue, $firstDataRow)) {
         return array_column($this->data, $fieldNameValue, $fieldNameKey);    
    }
    return false;
  }

 /*
  * get 1 dimensional numerical array from column with fieldName
  * @param string fieldname
  * @return 1 dimensional numerical array or false if error
  */  
  public function fetchColumn($fieldName){
    //ignore select
    if(array_key_exists($fieldName, reset($this->data))){
      return array_column($this->data, $fieldName);    
    }
    return false;
  }

 /*
  * get 1 dimensional unique numerical array from column with fieldName
  * @param string fieldname
  * @return 1 dimensional numerical array or false if error
  */  
  public function fetchColumnUnique($fieldName,$sort_flags = SORT_REGULAR){
    $result = $this->fetchColumn($fieldName);
    if(is_array($result)) {
      $result = array_values(array_unique($result));
      sort($result,$sort_flags);
      return $result;
    }
    return false;    
  }
  
  
 /*
  * get the array as raw (ignore select)
  * @return array
  */  
  public function fetchRaw(){
    return $this->data;
  }

 /*
  * @param array $groups: array of max. 2 valid Fieldnames (key)
  * @return array of tabeles with $groupName as key
  */  
  public function fetchGroup(array $groups){
    if(count($groups) == 0) {
      throw new \InvalidArgumentException("No groups given");
    }      
    //check if $group exists
    $firstRow = reset($this->data);
    foreach($groups as $groupName) {
      if(!array_key_exists($groupName, $firstRow)){
        $msg = "Unknown fieldname '$groupName' ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }
    }
       
    if($this->selectKeys !== null){  //check if all groups selected
      $validGroups = array_intersect($groups, $this->selectKeys);
      if($validGroups != $groups) {
        $msg = "All groups must be selected ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }
    }
    
    return $this->groupBySubarrayValue(
      $this->getSelectData($this->data,$this->selectKeys),
      $groups
    );    
  }
  
 /*
  * get the field name (key) for given index
  * @param integer $index 
  * @return string field name or a array of fieldnames if index = null
  * return false if index not exists
  */
  public function fieldNameRaw($index = null){
    $keys = array_keys(reset($this->data));
    if($index === null) return $keys;   
    return array_key_exists($index,$keys) ? $keys[$index] : false;
  }  

 /*
  * for JsonSerializable Interface
  * may use  json_encode($sqlArrObj)
  * return array 
  */  
  public function jsonSerialize(){
    return $this->fetchAll();
  }
  
 /*
  * for Countable Interface
  * may use  count($sqlArrObj)
  * @return integer 
  */  
  public function count(){
    return iterator_count($this);
  }

  
 /*
  * remove first row and use it for keys
  * @return $this
  */
  public function firstRowToKey(){
    $keys = array_map('strval',reset($this->data));
    $data = [];
    $first = true;
    foreach($this->data as $row){
      if($first) $first = false;
      else $data[] = array_combine($keys, $row);
    }
    $this->data = $data;
    return $this;    
  }
  
 /*
  * Iterator Methods
  */
    public function rewind() {
      reset($this->data);
    }

    public function current() {
      //return current($this->data);
      return $this->getSelectRow(current($this->data));
    }

    public function key() {
      return key($this->data);
    }

    public function next() {
      return $this->getSelectRow(next($this->data));
    }

    public function valid() {
      return $this->current() !== false;
    }
    
    public function reset(){
      return $this->getSelectRow(reset($this->data));  
    }
    
    /*
    public function seek($no){
      debug::write($no);
    }
    */
  
  
  //prepare sqlOrderTerm for sort-function
  protected function setSort($sqlOrderTerm = ""){
    $firstDataRow = reset($this->data);
    $sqlObjects = $this->splitarg($sqlOrderTerm);
    
    foreach($sqlObjects as $i => $sqlObj){
      //$sqlObj->name, $sqlObj->as, $sqlObj->fct,
      //$sqlObj->fpar, $sqlObj->term, $sqlObj->rest
      if($sqlObj->fct){
        if(!array_key_exists($sqlObj->fct,$this->userFct)) {
          $msg = "Unknown Function  '".$match['function']."' ".__METHOD__;
          throw new \InvalidArgumentException($msg);
        }
        //$sqlObj->fpar as array
        $parArr = [];
        foreach($this->splitarg($sqlObj->fpar) as $parObject){
          $parArr[] =  $parObject->term;
        }
        $sqlObjects[$i]->fpar = $parArr;
      }
      else {
        //only name 
        if(!array_key_exists($sqlObj->name,$firstDataRow)) {
          //error
          $msg = "Unknown Field-Name '".$sqlObj->name."' ".__METHOD__." near '".$sqlObj->term."'";
          throw new \InvalidArgumentException($msg);
        }
      }
      //DESC ?
      $sqlObjects[$i]->desc = (stripos($sqlObj->rest,"DESC") !== false);
      $sqlObjects[$i]->flag = (stripos($sqlObj->rest,"NATURAL") !== false) ? "NATURAL" : "";
    }
    return $sqlObjects;
  }
 
  protected function sortFunction($a,$b){
    foreach($this->sqlSort as $sortInfo){
      $cmp = 0;
      if($sortInfo->fct) {
        //function
        $curFctParA = $curFctParB = [];
        foreach($sortInfo->fpar as $fpar){
          $trimPar = trim($fpar,"\"'"); 
          if($trimPar == $fpar) {
            //$fpar is field-Name 
            $curFctParA[] = $a[$fpar];
            $curFctParB[] = $b[$fpar];
          }
          else {
            //fpar is a fix string
            $curFctParA[] = $trimPar;
            $curFctParB[] = $trimPar;
          }
        }
        $fct = $this->userFct[$sortInfo->fct];
        $val_a = call_user_func_array($fct, $curFctParA);
        $val_b = call_user_func_array($fct, $curFctParB);
      }
      else {
        //field
        $val_a = $a[$sortInfo->name];
        $val_b = $b[$sortInfo->name];
      }
      $cmp = $this->compare($val_a, $val_b, $sortInfo->flag);
      
      if($sortInfo->desc) $cmp = -$cmp;
      if($cmp != 0) return $cmp;
    }
    return $cmp;
  }
  
 /*
  * filter all rows with field is like any element from array
  * @param $fieldName: key from a column
  * @param $inList : List of like-Terms
  * @param $flagAll : true all likes must contain in fieldName
  *                   false also one likes must contain in fieldName
  * @return $this
  */  
  private function filterLike($fieldName, $inList, $flagAll = true){
    $firstRowData = reset($this->data);
    if(!array_key_exists($fieldName, $firstRowData)){
        $msg = "Unknown fieldname '$fieldName'  ".__METHOD__;
        throw new \InvalidArgumentException($msg);
    }
    $isFieldInteger = is_integer($firstRowData[$fieldName]);
    if(is_string($inList)) {
      $inList = explode(',',$inList);
    }
    foreach($this->data as $key => $row){
      $fieldValue = $row[$fieldName];
      foreach($inList as $like){
        $cmp = $isFieldInteger 
          ? ($fieldValue == $like)
          : (stripos($fieldValue,$like) !== false)
        ;
        if($cmp != $flagAll) break;
      }
      if($cmp) continue;
      //delete row if not any like
      unset($this->data[$key]);
    }
    $this->data = array_values($this->data);
    return $this;
  }

  
 /*
  * Join On
  * @param $ref array Reference
  * @param $idRef name id for ON from Reference-Array
  * @param $refId name id Basis-Array
  * @param $joinTyp 'left' or 'inner'
  * @return $this
  
  */
  private function joinOn($ref, $tableAlias, $idRef, $refId, $joinTyp = "inner"){
    if($ref instanceof tableArray) {
      $ref = $ref->fetchAll(); 
    }
    $firstRowRef = reset($ref);
    if(!array_key_exists($idRef, $firstRowRef)){
        $msg = "Unknown fieldname '$idRef' Referenz ".__METHOD__;
        throw new \InvalidArgumentException($msg);
    }
    $firstRowData = reset($this->data);
    if(!array_key_exists($refId, $firstRowData)){
        $msg = "Unknown fieldname '$refId'  ".__METHOD__;
        throw new \InvalidArgumentException($msg);
    }
    //all keys from $ref exclude 
    $refAddKeys = array_keys(array_diff_key($firstRowRef,[$idRef => null]));
    $ref = array_column($ref,null,$idRef);
    foreach($this->data as $iData => $rowData){
      $curRefId = $rowData[$refId];
      if(array_key_exists($curRefId, $ref)){
        //ref exists -> add fields
        $refRow = $ref[$curRefId];
        
        foreach($refAddKeys as $iRef) {
          $this->data[$iData][$tableAlias.'.'.$iRef] = $refRow[$iRef];
        }
      }
      elseif($joinTyp == 'left') {
        //set elements null
        foreach($refAddKeys as $iRef) {
          $this->data[$iData][$tableAlias.'.'.$iRef] = null;
        }
      }
      else {
        //delete data row for inner join
        unset($this->data[$iData]);
      }
    }
    return $this;
  }
  
  //change Class to continue method chaining 
  public function toClass(/*'className', ...args */){
    $args = func_get_args();
    $class = array_shift($args);
    return new $class($this,...$args);
  }

  
  //private
  private function compare($a, $b, $flag){
    if($flag == "") {
      if($a == $b) return 0;
      return $b < $a ? 1: -1;
    }elseif($flag == "NATURAL") {
      return strnatcmp($a,$b); 
    }
  }
  
  //
  private function arrayFlatten(array $array, $delimiter = '.',$prefix = '') {
    $result = array();
    foreach($array as $key=>$value) {
      if($value instanceof stdClass) $value = (array)$value;
      if(is_array($value)) {
        if(empty($value)) {
          $result[$prefix.$key.$delimiter] = "";  //empty array
        } else {
          $result += $this->arrayFlatten($value, $delimiter, $prefix.$key.$delimiter);
        }
      }
      else {
        $result[$prefix.$key] = $value;
      }
    }
    return $result;
  }

 /*
  * return first fieldname if is invalid, false if nothing found
  */
  private function invalidFieldNames(array $fields){
    $firstRow = reset($this->data);
    foreach($fields as $fieldName){
      if(!array_key_exists($fieldName, $firstRow)){
        return $fieldName;
      }
    }
    return false;
  }

 /*
  * split 'f1,fkt(f1,"text,text2"),f3)'
  * @return array of objects with ->name, ->as, ->fct, ->fpar, ->term
  */
  protected function splitarg($str)
  {
      $str = preg_replace('~\R~',' ',$str).",";
      $tokens = preg_split("~([,\'\(\)\"])~",$str,0,PREG_SPLIT_DELIM_CAPTURE+PREG_SPLIT_NO_EMPTY);
      $arr=[];
      $delim = '';
      $openBracked = false;
      $arg = (object)null;
      $arg->fpar = $arg->fct = $arg->as = $arg->name = $strArg = "";  
          
      foreach($tokens as $itok => $tok){
          if($tok == "," AND $delim == "" AND !$openBracked){
            $strArg = trim($strArg);
            $arg->term = $strArg;
            if($strArg != ""){
              //rest
              $remove = $arg->name;
              if($arg->fct) $remove .= "(".$arg->fpar.")";
              $pos = strpos($strArg,$remove);
              $arg->rest = $pos === 0 ? trim(substr($strArg,strlen($remove))) : $strArg;
              $arr[] = $arg;
              $arg = (object)null;
              $arg->fpar = $arg->fct = $arg->as = $arg->name = $strArg = ""; 
            }
          }
          else{
              if($strArg == "") $arg->name = strtok($tok," ");
              $strArg .= $tok;
              if($openBracked AND $tok != ")") $arg->fpar .= $tok; 
              if($tok == "(") {
                  $openBracked = true;
                  if($itok > 0) $arg->fct = trim($tokens[$itok-1]);
                  $arg->fpar = "";
              }
              elseif($tok == ")")  $openBracked = false;
              elseif($tok == '"' OR $tok == "'") {
                  if($delim == "") $delim = $tok;
                  elseif($delim == $tok) $delim = "";
              }
              elseif(($posAs = stripos($tok," AS ")) !== false){
                  $arg->as = trim(substr($tok,$posAs+4));
                  $arg->name = trim(substr($tok,0,$posAs));
              }
          }
      }
      return $arr;
  }
  
 /*
  * process $this->selectKeys for $data
  */
  protected function getSelectData(array $data, $selectKeys = null){
    if($data === []) return [];
    //select fields and sort cols
    if($selectKeys === null) {
      $selectKeys = $this->selectKeys;
      if($selectKeys === null) { //All
        $selectKeys = array_keys(reset($this->data));
      }
    }
    $fct = function($row) use($selectKeys) {
      $newRow = [];
      foreach($selectKeys as $selKey){
        if(array_key_exists($selKey,$row)) $newRow[$selKey] = $row[$selKey];
      }
      return $newRow;
    };
    return array_map($fct, $data);
  }

  protected function getSelectRow($row){
    if(empty($row)) return $row;
    $selectKeys = $this->selectKeys;
    if($selectKeys === null) { //All
      return $row;
    }
    $newRow = [];
    foreach($selectKeys as $selKey){
      if(array_key_exists($selKey,$row)) $newRow[$selKey] = $row[$selKey];
    }
    return $newRow;
  }
   
 /*
  * @param input array
  * @param groups array with max. 3 fieldNames
  * @return array multidimensional
  */
  protected function groupBySubarrayValue(array $input, array $groups){
    $arr = [];
    $groupCount = count($groups);
    $group0 = $groups[0];
    if($groupCount == 1) { 
      foreach($input as $key => $row){
        $arr[$row[$group0]][$key] = $row;
      }
    }
    elseif($groupCount == 2) {
      foreach($input as $key => $row){
        $arr[$row[$group0]][$row[$groups[1]]][$key] = $row;
      }
    }
    else { //max 3 groups
      foreach($input as $key => $row){
        $arr[$row[$group0]][$row[$groups[1]]][$row[$groups[2]]][$key] = $row;
      }
    }  
    return $arr;    
  }
  
  
 /* 
  * fills rows so that all have the same number of elements
  * return true if array modify, other false
  */
  protected function rectify(){
    $modify = false;
    $maxColCount = 0;
    foreach($this->data as $row){
      $curCount = count($row);
      if($curCount > $maxColCount) $maxColCount = $curCount;
    }
    //fill missing with null
    foreach($this->data as $i => $row){
      $curCount = count($row);
      if($maxColCount > $curCount){
        $modify = true;
        $this->data[$i] = array_merge($row,array_fill(0,$maxColCount - $curCount, NULL));
      }      
    }
    return $modify;
  }
 
}