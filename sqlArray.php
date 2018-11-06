<?php
/**
.---------------------------------------------------------------------------.
|  Software: Array functions with Syntax like SQL                           |
|  @Version: 1.22                                                           | 
|  Date: 2018-11-05                                                         |
|  PHPVersion >= 7.0                                                        |
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
class sqlArray implements JsonSerializable{
  private $userFct = [];
  private $sqlSort = [];  //internal
  private $selectKeys = null;  //array with valid keys after SELECT, default null = Alle 

  private $data;  //2.dim 
  
  const REGEX_FIELD = '[\w\-\.]+';
  const REGEX_AS = '[\w\-\.]+';
  
 
 /*
  * @param array : 2 dim array
  */
  public function __construct(array $data = []){
    
    $firstRow = reset($data);
    if(is_object($firstRow)){
      $firstRow = (array)$firstRow;
      foreach($data as $i => $row){
        $data[$i] = (array)$row;
      }
    }
    if(!is_array($firstRow)) {
      $msg = "Parameter must a array with dimension 2".__METHOD__;
      throw new \InvalidArgumentException($msg);
    }
    $this->data = $data;
    $this->userFct = array(
      'UPPER' => function($val){return strtoupper($val);},
      'LOWER' => function($val){return strtolower($val);},
    );
  }

 /*
  * create a instance
  * @param $data array : 2 dim array
  * @return instance of sqlArray
  */
  public static function create(array $data = []){
    return new static($data);
  }

 /*
  * create a instance from JSON-String
  * @param $jsonStr : represents a 2-dimensional array
  * @return instance of sqlArray
  */
  public static function createFromJson($jsonStr){
    return new static(json_decode($jsonStr, true));
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
    $this->setSort($sqlOrderTerm);
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
    //in brackets replace comma with semocolon 
    $colKeys = preg_replace_callback(
      '~\([^\(\)]+\)~', 
      function($val){return str_replace(",",";",$val[0]);},
      $colKeys
    );
    $colKeys = array_map(
      function($val){return trim(str_replace(";",",",$val));},
      explode(',',$colKeys)
    );
    //function(field) AS asName
    $regExFct = '~^(?P<function>\w+) *\((?P<field>('.self::REGEX_FIELD.',?)+)\) +AS +(?P<as>'.self::REGEX_AS.')$~i';
    //field AS asName
    $regExFieldAs = '~^(?P<field>'.self::REGEX_FIELD.') +AS +(?P<as>'.self::REGEX_AS.')$~i';
    $validKeys = array_keys(reset($this->data));
    foreach($colKeys as $colKeyIndex => $key){
      if(in_array($key,$validKeys)) continue;  //fieldname solo
      
      $isFieldAs = (bool)preg_match($regExFieldAs,$key,$match);
      $isFct = $isFieldAs ? false : (bool)preg_match($regExFct,$key,$match);
      if($isFct or $isFieldAs){
        //check if field exists
        $fieldNames = array_map('trim',explode(',',$match['field']));
        //$fieldName = $match['field']; //name or name1,name2..
        $validFields = array_intersect($fieldNames,$validKeys);
        if($fieldNames == $validFields){
          //check if function exists
          $keyFunc = $isFct ? $match['function'] : "";
          if(($isFct AND array_key_exists($keyFunc,$this->userFct)) OR $isFieldAs){
            $asName = $match['as'];
            //add column
            foreach($this->data as $keyData => $row){
              $this->data[$keyData][$asName] = $isFct 
                //? $this->userFct[$keyFunc]($row[$fieldNames[0]])
                ? call_user_func_array(
                    $this->userFct[$keyFunc], 
                    array_intersect_key($row, array_flip($fieldNames))
                  )
                : $row[$fieldNames[0]];
            }
            //replace term with asName
            $colKeys[$colKeyIndex] = $asName;
            $validKeys[] = $asName;  //for check
          }
          else { 
            $msg = "Unknown Function  '".$match['function']."' ".__METHOD__;
            throw new \InvalidArgumentException($msg);
          }
        }
        else {
          $msg = "Unknown fieldname '$fieldName' ".__METHOD__;
          throw new \InvalidArgumentException($msg);
        }
      }
      else {
        $msg = "Unknown fieldname '$key' ".__METHOD__;
        throw new \InvalidArgumentException($msg);
      }
    }
    $this->selectKeys = $colKeys;
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
  * filter all rows if $callback returns true
  * @param $callback: userfunction with parameter $row
  * if $callback == null: remove all rows with a null value 
  * @return $this
  */  
  public function filter($callback = null){
    if(empty($this->data)) return [];
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
  * inner Join On
  * @param $ref array Reference
  * @param $idRef name id for ON from Reference-Array
  * @param $refId name id Basis-Array
  * @return $this
  */
  public function innerJoinOn(array $ref, $idRef, $refId){
    return $this->joinOn($ref, $idRef, $refId, 'inner');
  }

 /*
  * left Join On
  * @param $ref array Reference
  * @param $idRef name id for ON from Reference-Array
  * @param $refId name id Basis-Array
  * @return $this
  */
  public function leftJoinOn(array $ref, $idRef, $refId){
    return $this->joinOn($ref, $idRef, $refId, 'left');
  }
  
  
 /*
  * convert to pivot table
  * @param $group Field name for grouping
  * @param $pivot Field name for pivot
  * @param $case Field name for case
  * @return $this
  */  
  public function pivot($group, $pivot, $case){
    $newData = [];
    foreach($this->data as $row){
      $newData[$row[$group]][$group] = $row[$group];
      $pivKey = $pivot.'.'.$row[$case];
      $newData[$row[$group]][$pivKey] = $row[$pivot];
    }
    $this->data = $newData;
    return $this;
  }
 
 /*
  * delete all rows < offset
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
  * delete all rows > limt
  * @return $this
  */  
  public function limit($number){
    $i = 0;
    foreach($this->data as $key => $row){
      $i++;
      if($i <= $number) continue;
      unset($this->data[$key]);
    }
    $this->data = array_values($this->data);
    return $this;
  }

 /*
  * get the array
  * @return array
  */  
  public function fetchAll(){
    if($this->data === []) return [];
    //select fields and sort cols
    $selectKeys = $this->selectKeys;
    if($selectKeys === null) { //All
      $selectKeys = array_keys(reset($this->data));
    }
    $fct = function($row) use($selectKeys) {
      $newRow = [];
      foreach($selectKeys as $selKey){
        if(array_key_exists($selKey,$row)) $newRow[$selKey] = $row[$selKey];
      }
      return $newRow;
    };
    return array_map($fct, $this->data);
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
  * for JsonSerializable Interface
  * may use  json_encode($sqlArrObj)
  */  
  public function jsonSerialize(){
    return $this->fetchAll();
  }
  
  
 /*
  * get a array(key => Value)
  * @return array
  */  
  public function fetchKeyValue($fieldNameKey, $fieldNameValue){
    //ignore select
    $selectKeys = array_keys(reset($this->data));
    if(in_array($fieldNameKey, $selectKeys) AND
       in_array($fieldNameValue, $selectKeys)) {
         return array_column($this->data, $fieldNameValue, $fieldNameKey);    
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

  public function setSort($sqlOrderTerm = ""){
    $sqlOrderTerm = preg_replace('~\R~',' ',$sqlOrderTerm);
    $sqlOrders = array_map('trim',explode(',',$sqlOrderTerm));
    
    $patterns = array(
      'field' => '~^(?P<field>'.self::REGEX_FIELD.') *(?P<desc>ASC|DESC)? *(?P<flag>NATURAL|NUMERIC|STRING)?$~i',
      'function' => '~^(?P<function>\w+) *\((?P<field>'.self::REGEX_FIELD.')\) *(?P<desc>ASC|DESC)? *(?P<flag>NATURAL|NUMERIC|STRING)?$~i',
      'like' => '~(?P<field>'.self::REGEX_FIELD.') +like +(?P<param>[^ ]+) *(?P<desc>ASC|DESC)?~i',
    );

    $this->sqlSort = [];
    foreach($sqlOrders as $i => $sqlPart){
      foreach($patterns as $typ => $regEx){
        if(preg_match($regEx,$sqlPart,$match)) break;
      }
      if(empty($match)){
        //error
        $msg = "Syntax-Error Parameter ".__METHOD__." near '".$sqlPart."'";
        throw new \InvalidArgumentException($msg);
      } else {
        $match = array_filter($match,'is_string',ARRAY_FILTER_USE_KEY);
        $match['typ'] = $typ;
        if($typ == 'function' AND !array_key_exists($match['function'],$this->userFct)) {
          $msg = "Unknown Function  '".$match['function']."' ".__METHOD__;
          throw new \InvalidArgumentException($msg);
        }
        if(!isset($match['param'])) $match['param'] = ""; 
        if(!isset($match['flag'])) $match['flag'] = ""; 
        $match['desc'] = (isset($match['desc']) AND strtolower($match['desc']) == 'desc');
        $this->sqlSort[$i] = $match;
      }
     
    }
    return $this;
  }
  
  public function sortFunction($a,$b){
    foreach($this->sqlSort as $sortInfo){
      $cmp = 0;
      $val_a = $a[$sortInfo['field']];
      $val_b = $b[$sortInfo['field']];
      if($sortInfo['typ'] == 'field') {
        $cmp = $this->compare($val_a, $val_b, $sortInfo['flag']);
      }
      elseif($sortInfo['typ'] == 'like') {
        $search = trim($sortInfo['param']," %'\"");
        $cmpA = (int)(stripos($val_a,$search) !== false) ;
        $cmpB = (int)(stripos($val_b,$search) !== false);
        if($cmpB != $cmpA) $cmp = $cmpB < $cmpA ? 1 : -1;
      }
      elseif($sortInfo['typ'] == 'function') {
        $userFct = $this->userFct[$sortInfo['function']];
        $val_a = $a[$sortInfo['field']];
        $val_b = $b[$sortInfo['field']];
        $val_a = $userFct($val_a);
        $val_b = $userFct($val_b);
        $cmp = $this->compare($val_a, $val_b, $sortInfo['flag']);      }
      if($sortInfo['desc']) $cmp = -$cmp;
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
  private function joinOn(array $ref, $idRef, $refId, $joinTyp = "inner"){
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
    //all keys from 4ref not contain in data
    $refAddKeys = array_keys(array_diff_key($firstRowRef,$firstRowData));
    $ref = array_column($ref,null,$idRef);
    foreach($this->data as $iData => $rowData){
      $curRefId = $rowData[$refId];
      if(array_key_exists($curRefId, $ref)){
        //ref exists -> add fields
        $refRow = $ref[$curRefId];
        foreach($refAddKeys as $iRef) {
          $this->data[$iData][$iRef] = $refRow[$iRef];
        }
      }
      elseif($joinTyp == 'left') {
        //set elements null
        foreach($refAddKeys as $iRef) {
          $this->data[$iData][$iRef] = null;
        }
      }
      else {
        //delete data row for inner join
        unset($this->data[$iData]);
      }
    }    
    return $this;
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
  
}
