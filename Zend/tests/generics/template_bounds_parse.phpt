--TEST--
Generics M1: bounds parse — T implements Iface and T extends ClassName, including via use-alias
--FILE--
<?php
use Countable as Cnt;

class Box<T implements Cnt> {
    public function set(T $v): void {}
}
class Pair<K implements Stringable, V> {}
class Node<T extends Exception> {
    public function wrap(T $e): T { return $e; }
}
class Mixed2<K extends ArrayObject, V implements Traversable> {}

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
