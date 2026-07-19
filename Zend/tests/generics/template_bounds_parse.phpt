--TEST--
Generics M1: bounds (T implements Iface) parse, including via use-alias
--FILE--
<?php
use Countable as Cnt;

class Box<T implements Cnt> {
    public function set(T $v): void {}
}
class Pair<K implements Stringable, V> {}

var_dump(class_exists('Box', false));
var_dump(class_exists('Pair', false));
?>
--EXPECT--
bool(true)
bool(true)
