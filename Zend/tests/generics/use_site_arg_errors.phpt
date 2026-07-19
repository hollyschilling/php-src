--TEST--
Generics M2: type parameters cannot be used as type arguments yet (concrete-only v0)
--FILE--
<?php
class Vec<T> {}
class Box<T> {
    public function f(Vec<T> $x): void {}
}
?>
--EXPECTF--
Fatal error: Cannot use type parameter T as a generic type argument (type arguments must be concrete in this version) in %s on line %d
