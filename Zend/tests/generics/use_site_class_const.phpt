--TEST--
Generics M2: Vec<T>::class yields the canonical mangled name without loading anything
--FILE--
<?php
namespace App;

use Countable as Cnt;

class Vec<T> {}

var_dump(Vec<int>::class);
var_dump(Vec<Cnt>::class);
var_dump(Vec<\stdClass>::class);
var_dump(Vec<Vec<int>>::class);
var_dump(\App\Vec<string>::class);
?>
--EXPECT--
string(12) "App\Vec<int>"
string(18) "App\Vec<Countable>"
string(17) "App\Vec<stdClass>"
string(21) "App\Vec<App\Vec<int>>"
string(15) "App\Vec<string>"
