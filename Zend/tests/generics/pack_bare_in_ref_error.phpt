--TEST--
Generics packs: a pack in a deferred reference must be spread with ...
--FILE--
<?php
interface I<T> {}
class X<...Ts> implements I<Ts> {}
?>
--EXPECTF--
Fatal error: Type parameter pack Ts must be expanded with ... in a generic type argument in %s on line %d
