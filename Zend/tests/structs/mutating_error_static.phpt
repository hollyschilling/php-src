--TEST--
Structs: mutating methods cannot be static
--FILE--
<?php
struct S {
    public int $x = 0;
    public static function f() mutating: void {}
}
?>
--EXPECTF--
Fatal error: Mutating method S::f() cannot be static in %s on line %d
