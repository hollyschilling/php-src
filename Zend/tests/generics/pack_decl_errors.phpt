--TEST--
Generics packs: a template may declare at most one pack
--FILE--
<?php
class X<...A, ...B> {}
?>
--EXPECTF--
Fatal error: Generic class may declare at most one type parameter pack (parameter B) in %s on line %d
