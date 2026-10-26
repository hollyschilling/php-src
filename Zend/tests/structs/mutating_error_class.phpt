--TEST--
Structs: mutating methods cannot be declared outside a struct
--FILE--
<?php
class C {
    public function f() mutating: void {}
}
?>
--EXPECTF--
Fatal error: Cannot declare mutating method C::f() outside a struct in %s on line %d
