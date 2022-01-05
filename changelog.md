# tableArray

## Version 2.6 (2022-01-03)
* Constructor method extended. An optional filter function can now be used to collect all rows.

## Version 2.5 (2021-12-09)
* Modify method collectChilds, optional with flatkeys
* Methods filterLikeIn,filterLikeAll: Add optional Parameter preserveKey
* Redesign wildcardMatch for flatkeys

## Version 2.4 (2021-11-22)
* Add aggregate functions JSON, ARRAY
* Add csv option eol

## Version 2.3 (2021-06-22)
* clean up for phpstan up to level 5

## Version 2.2 (2021-04-10)
* createFromXML now works with XML namespaces

## Version 2.1 (2021-04-21)
* Add method merge
* Add public static function allRowKeys(array$data)
* Redesign method rectify
* Add function split

## Version 2.0 (2020-12-15)
* Add method filterGroupAggregate
* Aggregate functions: MIN, MAX, SUM, AVG, COUNT, CONCAT
* replaces methods like filtergroupMax, filtergroupMin that have been removed 

## Version 1.89 (2020-11-25)
* Modify FLOATVAL: Accepts further parameters, works like number_format

## Version 1.88 (2020-11-16)
* Add method filterEqual

## Version 1.85 (2020-09-28)
* Add function implode

## Version 1.81 (2020-08-09)
* Modify fetchLimit: accepts second optional parameter start

## Version 1.80
* Add method createFromGroupedArray

## Version 1.78
* Limit now accepts a negative value
* Add function concat

## Version 1.75 (2019-12-25)
* modify method filterUnique: Argument array or null for all

## Version 1.74 (2019-11-20)
* Add method createFromString

## Version 1.73 (2019-11-16)
* Add method fieldAsKey
* Add method transpose

## Version 1.72 
* modify method createFromOneDimArray

## Version 1.71 
* implements countable interface

## Version 1.70 
* Add method walk

## Version 1.68 
* modify method createFromOneDimArray

## Version 1.67 (2019-05-16)
* Add method filterGroupSum

## Version 1.60
* Add method filterGroupMax
* Add method filterGroupMin

## Version 1.0 (2018-12-10)
first Version 

