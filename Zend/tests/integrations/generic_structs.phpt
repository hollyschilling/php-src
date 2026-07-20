--TEST--
Cross-feature: generic structs — value semantics + substituted enforcement + mutating methods
--FILE--
<?php
struct Box<T> {
    public function __construct(public T $v) {}
    public function set(T $v) mutating: void { $this->v = $v; }
}
struct Pair<K, V> {
    public function __construct(public K $k, public V $v) {}
}

$b = new Box<int>(41);
$c = $b;             // value copy
$b->set(42);
var_dump(get_class($b));
var_dump($b->v);
var_dump($c->v);
try { $b->set("x"); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
try { new Box(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump(Box<int>::class);

$p = new Pair<string, int>("a", 1);
var_dump($p instanceof Pair<string, int>);
var_dump($p instanceof Pair<int, string>);
?>
--EXPECTF--
string(8) "Box<int>"
int(42)
int(41)
Box<int>::set(): Argument #1 ($v) must be of type int, string given, called in %s on line %d
Cannot instantiate generic class Box without type arguments
string(8) "Box<int>"
bool(true)
bool(false)
