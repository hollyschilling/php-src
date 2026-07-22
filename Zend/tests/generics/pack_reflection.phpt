--TEST--
Generics: Reflection surface for packs and deferred parents
--FILE--
<?php
class Vec<T> {
    public function push(T $v): void {}
}
class MyVec<T> extends Vec<T> {}
class Pair<...Tp implements Countable, R> {}

// variadic flag per parameter
$r = new ReflectionClass('Pair');
var_dump($r->getGenericTypeParameters());

// deferred parent ref: raw on the template, via template for instantiations
$m = new ReflectionClass('MyVec');
var_dump($m->getGenericParentName());
$mi = new ReflectionClass('MyVec<int>');
var_dump($mi->getGenericParentName());              // instance: null (generic_params on template only)
var_dump($mi->getGenericTemplate()->getGenericParentName());
var_dump($mi->getParentClass()->getName());

// non-generic and concrete-parent classes answer null
var_dump((new ReflectionClass('Vec'))->getGenericParentName());

// flat args across pack instantiation
$pi = new ReflectionClass('Pair<ArrayObject,ArrayIterator,int>');
var_dump($pi->getGenericTypeArguments());
?>
--EXPECT--
array(2) {
  [0]=>
  array(4) {
    ["name"]=>
    string(2) "Tp"
    ["boundKind"]=>
    string(10) "implements"
    ["bound"]=>
    string(9) "Countable"
    ["variadic"]=>
    bool(true)
  }
  [1]=>
  array(4) {
    ["name"]=>
    string(1) "R"
    ["boundKind"]=>
    NULL
    ["bound"]=>
    NULL
    ["variadic"]=>
    bool(false)
  }
}
string(6) "Vec<T>"
NULL
string(6) "Vec<T>"
string(8) "Vec<int>"
NULL
array(3) {
  [0]=>
  string(11) "ArrayObject"
  [1]=>
  string(13) "ArrayIterator"
  [2]=>
  string(3) "int"
}
