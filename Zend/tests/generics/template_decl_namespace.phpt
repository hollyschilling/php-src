--TEST--
Generics M1: type parameter is not namespace-qualified inside a namespaced template
--FILE--
<?php
namespace App;

class Box<T> {
    public function set(T $v): void {}
}

$m = new \ReflectionMethod('App\Box', 'set');
var_dump((string) $m->getParameters()[0]->getType());
?>
--EXPECT--
string(1) "T"
