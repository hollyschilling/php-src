--TEST--
Structs: magic methods (other than the constructor) cannot be mutating
--FILE--
<?php
struct S {
    public int $x = 0;
    public mutating function __invoke(): void {}
}
?>
--EXPECTF--
Fatal error: Cannot declare magic method S::__invoke() mutating in %s on line %d
