--TEST--
Generic bounds: a type parameter pack cannot appear in a bound
--FILE--
<?php
class Box<T> {}
class Bad<...Ts: Box<Ts>> {}
?>
--EXPECTF--
Fatal error: Type parameter pack Ts must be expanded with ... in a generic type argument in %s on line %d
