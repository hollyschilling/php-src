--TEST--
Composite (DNF) arguments: type parameters cannot be members in this version
--FILE--
<?php
class Vec<T> {}
class Pair<T> {
    public ?Vec<T|null> $v = null;
}
?>
--EXPECTF--
Fatal error: Type parameter T cannot be a member of a composite type argument (composite arguments must be concrete in this version) in %s on line %d
