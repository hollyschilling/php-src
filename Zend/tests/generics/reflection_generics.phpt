--TEST--
Generics: ReflectionClass introspection of templates, instantiations and bindings
--FILE--
<?php
interface Collection<T> { public function add(T $item): void; }
class Vec<T implements Countable> implements Collection<T> {
    public function add(T $item): void {}
}
class Bag implements Countable { public function count(): int { return 1; } }
class Pair<K, V> {}
class Plain {}

$rt = new ReflectionClass('Vec');
var_dump($rt->isGenericTemplate());
var_dump($rt->isGenericInstantiation());
var_dump($rt->isInstantiable());
var_dump($rt->getGenericTypeParameters());
var_dump($rt->getGenericInterfaceNames());
var_dump($rt->getGenericTemplate());

$ri = new ReflectionClass('Vec<Bag>');
var_dump($ri->isGenericTemplate());
var_dump($ri->isGenericInstantiation());
var_dump($ri->isInstantiable());
var_dump($ri->getGenericTypeArguments());
var_dump($ri->getGenericTemplate()?->getName());
var_dump($ri->getGenericTypeParameters()[0]['name']);

$scalars = new ReflectionClass('Pair<int,string>');
var_dump($scalars->getGenericTypeArguments());

$plain = new ReflectionClass('Plain');
var_dump($plain->isGenericTemplate());
var_dump($plain->isGenericInstantiation());
var_dump($plain->getGenericTypeParameters());
var_dump($plain->getGenericTypeArguments());
var_dump($plain->getGenericTemplate());
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
array(1) {
  [0]=>
  array(3) {
    ["name"]=>
    string(1) "T"
    ["boundKind"]=>
    string(10) "implements"
    ["bound"]=>
    string(9) "Countable"
  }
}
array(1) {
  [0]=>
  string(13) "Collection<T>"
}
NULL
bool(false)
bool(true)
bool(true)
array(1) {
  [0]=>
  string(3) "Bag"
}
string(3) "Vec"
string(1) "T"
array(2) {
  [0]=>
  string(3) "int"
  [1]=>
  string(6) "string"
}
bool(false)
bool(false)
array(0) {
}
array(0) {
}
NULL
