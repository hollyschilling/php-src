--TEST--
Generics M1: generic class template declaration parses; T survives as unqualified type
--FILE--
<?php
class Box<T> {
    private ?T $value = null;
    public function set(T $v): void { $this->value = $v; }
    public function get(): ?T { return $this->value; }
}
var_dump(class_exists('Box', false));
$m = new ReflectionMethod('Box', 'set');
var_dump((string) $m->getParameters()[0]->getType());
var_dump((string) (new ReflectionMethod('Box', 'get'))->getReturnType());
var_dump((string) (new ReflectionProperty('Box', 'value'))->getType());
?>
--EXPECT--
bool(true)
string(1) "T"
string(2) "?T"
string(2) "?T"
