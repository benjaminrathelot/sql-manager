# sql-manager
Simple PHP Class to work with PDO + Equivalent for NodeJS : same functions

Syntax :

PHP
$t = new Apm('myTable'); 

NODEJS
const Asm = require("./asm.br.js");
let t = new Asm('myTable');

Functions :

get() : get with arguments, example : $result = $t->get(["country"=>"France"]) / let result = t.get({country:"France"});
one() : get first return element
exists()...
whr(clause) : set a where clause, example : ["country"=>"France"]
insert(data as array/object)
delete() using current where clause or the one specified

id(whereClause) using current where clause or the one specified : assigns all values to the current object and sets the where clause to id=data id field:
=> enables attr function : attr(field, value[optional]) : $t->attr('name'); returns name of the current object (if ->id() function has been used), $t->attr('name', 'Bob'); updates the name for the current id

