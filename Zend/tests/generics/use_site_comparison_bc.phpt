--TEST--
Generics M2: comparison operators are untouched by generic-open lexing
--FILE--
<?php
const B = 2;
const C = 3;

$a = 1;
$b = 5;
var_dump($a < $b);
var_dump($b > $a);
var_dump(B < 1);
var_dump(C > 2);
var_dump(1 < 2);
var_dump(B < C);
function f($x, $y) { return [$x, $y]; }
var_dump(f(B < 1, C > 2));
var_dump(4 >> 1);
var_dump(1 << 3);
var_dump(B <= C, C >= B, B <=> C);
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
array(2) {
  [0]=>
  bool(false)
  [1]=>
  bool(true)
}
int(2)
int(8)
bool(true)
bool(true)
int(-1)
