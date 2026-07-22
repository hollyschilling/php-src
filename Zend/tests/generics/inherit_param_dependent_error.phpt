--TEST--
Generics: nested param-dependent inheritance arguments stay rejected (bare-args-only restriction)
--FILE--
<?php
class Box<T> {}
class Vec<T> {}
class MyVec<T> extends Vec<Box<T>> {}
?>
--EXPECTF--
Fatal error: Cannot use type parameter T as a generic type argument (type arguments must be concrete in this version) in %s on line %d
