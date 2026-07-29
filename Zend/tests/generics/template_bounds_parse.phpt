--TEST--
Generics M1: ':' bounds parse — interface, class and aliased bounds
--FILE--
<?php
use Countable as Cnt;

class Box<T: Cnt> {
    public function set(T $v): void {}
}
class Pair<K: Stringable, V> {}
class Node<T: Exception> {
    public function wrap(T $e): T { return $e; }
}
class Mixed2<K: ArrayObject, V: Traversable> {}

var_dump(class_exists('Box', false));
var_dump(class_exists('Pair', false));
var_dump(class_exists('Node', false));
var_dump(class_exists('Mixed2', false));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
