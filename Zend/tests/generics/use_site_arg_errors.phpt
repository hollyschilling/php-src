--TEST--
Generics: nested param-dependent type arguments stay rejected (bare-args-only restriction)
--FILE--
<?php
class Vec<T> {}
class Box<T> {}
class C<T> {
    public function f(Vec<Box<T>> $x): void {}
}
?>
--EXPECTF--
Fatal error: Cannot use type parameter T as a generic type argument (type arguments must be concrete in this version) in %s on line %d
