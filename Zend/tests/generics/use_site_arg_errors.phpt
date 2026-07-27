--TEST--
Generics: param-dependent composite members of union lists stay rejected, nested included
--FILE--
<?php
class Box<T> {}
class C<T> {
    public function f(): Box<Box<T>>|Countable { return new Box(); }
}
?>
--EXPECTF--
Fatal error: Parameterized type Box<Box<T>> is not supported inside a composite type (in this version) in %s on line %d
