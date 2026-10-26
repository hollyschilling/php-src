--TEST--
Generics: param-dependent extends — multi-level chains, mixed concrete tails, mixed argument lists
--FILE--
<?php
declare(strict_types=1);

// Chain ending in a concrete class.
class Base { public function tag(): string { return "base"; } }
class Mid<T> extends Base { public function mid(T $v): T { return $v; } }
class Top<T> extends Mid<T> { }

$t = new Top<int>();
var_dump($t->tag());
var_dump($t->mid(5));
var_dump($t instanceof Base);
var_dump($t instanceof Mid<int>);

// Mixed concrete + param argument list on the deferred parent.
class Pair<K, V> {
    public function __construct(public K $k, public V $v) {}
}
class StringKeyed<V> extends Pair<string, V> { }

$p = new StringKeyed<int>("id", 42);
var_dump($p->k, $p->v);
var_dump($p instanceof Pair<string,int>);
try {
    new StringKeyed<int>(3, 42);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
string(4) "base"
int(5)
bool(true)
bool(true)
string(2) "id"
int(42)
bool(true)
Pair<string,int>::__construct(): Argument #1 ($k) must be of type string, int given, called in %s on line %d
