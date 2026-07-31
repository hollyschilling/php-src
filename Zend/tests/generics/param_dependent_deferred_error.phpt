--TEST--
Deferred inheritance references keep bare arguments only (no composite members)
--FILE--
<?php
interface Collection<T> {}
class Vec<T> implements Collection<T|null> {}
?>
--EXPECTF--
Fatal error: Type parameter T cannot be a member of a composite type argument (composite arguments must be concrete in this version) in %s on line %d
