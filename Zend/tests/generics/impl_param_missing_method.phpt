--TEST--
Generics M4-lite: missing interface methods are reported at stamp time
--FILE--
<?php
interface Collection<T> { public function add(T $x): void; }
class Missing<T> implements Collection<T> {}
new Missing<int>();
?>
--EXPECTF--
Fatal error: Class Missing<int> contains 1 abstract method and must therefore be declared abstract or implement the remaining method (Collection<int>::add) in %s on line %d
