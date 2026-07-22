--TEST--
Generics packs: a pack cannot be used as a type
--FILE--
<?php
class X<...Ts> { public Ts $x; }
?>
--EXPECTF--
Fatal error: Type parameter pack Ts cannot be used as a type in %s on line %d
