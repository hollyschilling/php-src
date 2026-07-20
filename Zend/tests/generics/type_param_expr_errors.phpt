--TEST--
Generics M3: scalar type args cannot be used as classes; trait methods lack scope bindings
--FILE--
<?php
class Factory<T> {
    public function n(): string { return T::class; }
    public function create(): object { return new T(); }
}
$f = new Factory<int>();
var_dump($f->n());
try { $f->create(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

trait Maker<T> { public function mk(): object { return new T(); } }
class UsesMaker { use Maker<stdClass>; }
try { (new UsesMaker())->mk(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
string(3) "int"
Cannot use scalar type argument int as a class
Cannot resolve a type parameter when no generic binding is in scope
