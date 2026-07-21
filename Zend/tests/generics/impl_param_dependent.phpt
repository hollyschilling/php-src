--TEST--
Generics M4-lite: class Vec<T> implements Collection<T> resolves per instantiation
--FILE--
<?php
interface Collection<T> {
    const KIND = "collection";
    public function add(T $item): void;
    public function first(): ?T;
}

class Vec<T> implements Collection<T> {
    private array $items = [];
    public function add(T $item): void { $this->items[] = $item; }
    public function first(): ?T { return $this->items[0] ?? null; }
}

$v = new Vec<int>();
$v->add(41);
var_dump($v instanceof Collection<int>);
var_dump($v instanceof Collection<string>);
var_dump($v->first());
var_dump(Vec<int>::KIND);

function drain(Collection<int> $c): ?int { return $c->first(); }
var_dump(drain($v));
try { drain(new Vec<string>()); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

interface MapEntry<K, V> { public function key(): K; }
class Pair<K, V> implements MapEntry<K, V> {
    public function __construct(private mixed $k = null) {}
    public function key(): K { return $this->k; }
}
var_dump(new Pair<string, int>() instanceof MapEntry<string, int>);
var_dump(new Pair<string, int>() instanceof MapEntry<int, string>);

// interface template extending a generic interface param-dependently
interface Sorted<T> extends Collection<T> { public function sortBy(T $k): void; }
class SVec<T> implements Sorted<T> {
    public function add(T $x): void {}
    public function first(): ?T { return null; }
    public function sortBy(T $k): void {}
}
$s = new SVec<int>();
var_dump($s instanceof Sorted<int>);
var_dump($s instanceof Collection<int>);
?>
--EXPECTF--
bool(true)
bool(false)
int(41)
string(10) "collection"
int(41)
drain(): Argument #1 ($c) must be of type Collection<int>, Vec<string> given, called in %s on line %d
bool(true)
bool(false)
bool(true)
bool(true)
