--TEST--
Generics M1: generic interface template declaration parses
--FILE--
<?php
interface Collection<T> {
    public function add(T $item): void;
    public function first(): ?T;
}
var_dump(interface_exists('Collection', false));
$m = new ReflectionMethod('Collection', 'add');
var_dump((string) $m->getParameters()[0]->getType());
?>
--EXPECT--
bool(true)
string(1) "T"
