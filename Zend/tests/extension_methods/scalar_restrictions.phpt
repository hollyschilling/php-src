--TEST--
Scalar receivers are by value; generators work; FCC of scalar methods throws
--FILE--
<?php
extension array $a {
    function grow(): int { $a[] = 99; return count($a); }   // local copy only
    function walk(): \Generator { yield from $a; }          // generators work
}
extension string $s {
    function len(): int { return strlen($s); }
}

$arr = [1, 2];
var_dump($arr->grow());
var_dump($arr);                       // caller's array unchanged

foreach ([10, 20]->walk() as $v) { var_dump($v); }

try {
    $f = "abc"->len(...);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
var_dump("abc"->len());               // plain call still fine afterwards
?>
--EXPECT--
int(3)
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
int(10)
int(20)
Cannot create a first-class callable from a scalar extension method
int(3)
