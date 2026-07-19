--TEST--
Generics M1: qualified reference to a class shadowed by a type parameter is a compile error
--FILE--
<?php
class Box<T> {
    public function f(\T $x): void {}
}
?>
--EXPECTF--
Fatal error: Cannot reference class T inside generic template Box because the name is used by a type parameter in %s on line %d
