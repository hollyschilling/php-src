--TEST--
Generics: param-dependent composite members of union types are rejected at declaration
--FILE--
<?php
class Vec<T> {}
class U1<T> {
    public function h(Vec<T>|ArrayObject $p): void {}
}
?>
--EXPECTF--
Fatal error: Parameterized type Vec<T> is not supported inside a composite type (in this version) in %s on line %d
