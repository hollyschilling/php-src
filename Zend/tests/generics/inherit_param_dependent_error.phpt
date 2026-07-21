--TEST--
Generics M3: param-dependent inheritance (extends Vec<T>) is rejected, not silently mis-resolved
--FILE--
<?php
class Vec<T> {}
class MyVec<T> extends Vec<T> {}
?>
--EXPECTF--
Fatal error: Cannot use type parameter T as a generic type argument (type arguments must be concrete in this version) in %s on line %d
