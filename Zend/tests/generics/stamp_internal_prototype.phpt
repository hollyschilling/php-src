--TEST--
Stamping an instantiation whose template inherits an internal function (abstract prototype from an internal interface)
--FILE--
<?php

// A generic interface extending IteratorAggregate carries the internal
// abstract getIterator prototype in its function table. Stamping such an
// instantiation (or a class instantiation inheriting any internal method)
// must share the internal function as ordinary inheritance does, not clone
// it as an op_array.

interface Collection<T> extends IteratorAggregate {
    public function first(): T;
}

// Traversable arrives only through the deferred generic interface.
class Bag<T> implements Collection<T> {
    private array $items;
    public function __construct(array $i) { $this->items = array_values($i); }
    private function all(): array { return $this->items; }
    public function first(): T { return $this->items[0]; }
    public function getIterator(): Traversable { return new ArrayIterator($this->all()); }
}

$b = new Bag<int>([4, 5, 6]);
echo "first: ", $b->first(), "\n";

$out = "";
foreach ($b as $v) { $out .= $v; }
echo "foreach: $out\n";

var_dump($b instanceof Traversable);
var_dump($b instanceof Collection<int>);

// The interface instantiation itself is observable.
var_dump(interface_exists('Collection<int>'));
?>
--EXPECT--
first: 4
foreach: 456
bool(true)
bool(true)
bool(true)
