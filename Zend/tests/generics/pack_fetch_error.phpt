--TEST--
Generics packs: a pack cannot be fetched as a class (new/::class)
--FILE--
<?php
class X<...Ts> { public function f(): object { return new Ts(); } }
?>
--EXPECTF--
Fatal error: Type parameter pack Ts cannot be used as a type in %s on line %d
