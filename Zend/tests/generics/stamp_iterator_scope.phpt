--TEST--
Internal iterator/dim dispatch on a stamped instantiation runs with the instantiation's scope (not the template's)
--FILE--
<?php

// foreach and dim access dispatch through per-class caches of zend_function
// pointers (iterator_funcs_ptr / arrayaccess_funcs_ptr). Stamping must rebuild
// them against the rebound method clones; the template's cache would run the
// call with the template's scope and deny non-public access to members whose
// declaring class is the instantiation.

class Agg<T> implements IteratorAggregate {
    private array $items;
    public function __construct(array $i) { $this->items = array_values($i); }
    private function snapshot(): array { return $this->items; }
    public function getIterator(): Traversable { return new ArrayIterator($this->snapshot()); }
}

class Seq<T> implements Iterator {
    private int $pos = 0;
    protected array $items;
    public function __construct(array $i) { $this->items = array_values($i); }
    public function rewind(): void { $this->pos = 0; }
    public function valid(): bool { return $this->pos < count($this->items); }
    public function current(): mixed { return $this->items[$this->pos]; }
    public function key(): mixed { return $this->pos; }
    public function next(): void { $this->pos++; }
}

class Map<K, V> implements ArrayAccess {
    private array $store = [];
    public function offsetExists(mixed $o): bool { return isset($this->store[$o]); }
    public function offsetGet(mixed $o): mixed { return $this->store[$o] ?? null; }
    public function offsetSet(mixed $o, mixed $v): void { $this->store[$o] = $v; }
    public function offsetUnset(mixed $o): void { unset($this->store[$o]); }
}

// IteratorAggregate: getIterator dispatched internally, calls a private method.
$a = new Agg<int>([1, 2, 3]);
$out = "";
foreach ($a as $v) { $out .= $v; }
echo "agg: $out\n";

// Iterator: every hop (rewind/valid/current/key/next) touches a private cursor.
$s = new Seq<string>(["a", "b", "c"]);
$out = "";
foreach ($s as $k => $v) { $out .= "$k=$v "; }
echo "seq: ", rtrim($out), "\n";

// Second foreach re-enters through the cached zf_rewind.
$out = "";
foreach ($s as $v) { $out .= $v; }
echo "seq again: $out\n";

// ArrayAccess: dim handlers dispatch through arrayaccess_funcs_ptr.
$m = new Map<string, int>();
$m["x"] = 42;
var_dump($m["x"], isset($m["x"]));
unset($m["x"]);
var_dump(isset($m["x"]));

// A second instantiation of the same template gets its own rebuilt cache.
$b = new Agg<string>(["x", "y"]);
$out = "";
foreach ($b as $v) { $out .= $v; }
echo "second stamp: $out\n";
?>
--EXPECT--
agg: 123
seq: 0=a 1=b 2=c
seq again: abc
int(42)
bool(true)
bool(false)
second stamp: xy
