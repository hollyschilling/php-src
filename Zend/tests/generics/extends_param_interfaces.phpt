--TEST--
Generics: param-dependent extends — parent interfaces flow through the graft; deferred parent + deferred interfaces compose
--FILE--
<?php

// Traversable arrives only through the grafted parent.
class IB<T> implements IteratorAggregate {
    private array $items = [];
    public function __construct(array $i = []) { $this->items = $i; }
    private function all(): array { return $this->items; }
    public function getIterator(): Traversable { return new ArrayIterator($this->all()); }
}
class IC<T> extends IB<T> {}

$out = "";
foreach (new IC<int>([1, 2, 3]) as $v) { $out .= $v; }
echo "via parent: $out\n";
var_dump((new IC<int>) instanceof Traversable);

// The template itself implements Iterator while also having a deferred parent
// (the rebuilt dispatch cache must survive the graft reordering).
class IB2<T> { public function __construct(protected array $items = []) {} }
class IC2<T> extends IB2<T> implements IteratorAggregate {
    private function all(): array { return $this->items; }
    public function getIterator(): Traversable { return new ArrayIterator($this->all()); }
}
$out = "";
foreach (new IC2<int>([4, 5]) as $v) { $out .= $v; }
echo "own: $out\n";

// Deferred parent and deferred interface on the same template.
interface Coll<T> extends Countable {}
class DB<T> { public function __construct(protected array $items = []) {} }
class DC<T> extends DB<T> implements Coll<T> {
    public function count(): int { return count($this->items); }
}
$d = new DC<int>([1, 2, 3]);
var_dump(count($d));
var_dump($d instanceof Coll<int>);
var_dump($d instanceof Countable);
var_dump($d instanceof DB<int>);
?>
--EXPECT--
via parent: 123
bool(true)
own: 45
int(3)
bool(true)
bool(true)
bool(true)
