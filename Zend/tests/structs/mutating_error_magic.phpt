--TEST--
Structs: magic methods (other than the constructor) cannot be mutating
--FILE--
<?php
struct S {
    public int $x = 0;
    public function __invoke() mutating: void {}
}
?>
--EXPECTF--
Fatal error: Cannot declare magic method S::__invoke() mutating in %s on line %d
