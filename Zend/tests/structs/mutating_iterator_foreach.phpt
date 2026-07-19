--TEST--
Structs: Iterator's advancing members are colored; foreach iterates its own copy of a struct iterator
--FILE--
<?php

struct Range implements Iterator {
    public function __construct(private int $pos = 0, private int $end = 3) {}
    public function current(): mixed { return $this->pos * 10; }
    public function key(): mixed { return $this->pos; }
    public function next() mutating: void { $this->pos++; }
    public function rewind() mutating: void { $this->pos = 0; }
    public function valid(): bool { return $this->pos < $this->end; }
}

// foreach advances the struct iterator...
$r = new Range();
foreach ($r as $k => $v) {
    echo "$k => $v\n";
}

// ...by iterating the loop's own copy: the caller's value is not consumed.
var_dump($r->valid(), $r->key());

// Re-iteration works, and a shared value iterates without separating the alias.
$alias = $r;
$sum = 0;
foreach ($r as $v) { $sum += $v; }
var_dump($sum, $alias->key());

// A manual loop drives the caller's copy (ordinary mutating calls on a variable).
$m = new Range();
$out = [];
while ($m->valid()) { $out[] = $m->current(); $m->next(); }
var_dump($out === [0, 10, 20], $m->valid());

// IteratorAggregate may return a struct iterator.
class Bag implements IteratorAggregate {
    public function getIterator(): Iterator { return new Range(0, 2); }
}
$vals = [];
foreach (new Bag() as $v) { $vals[] = $v; }
var_dump($vals === [0, 10]);

// The retrofit is invisible to classes: uncolored implementations satisfy
// the colored requirements and foreach behaves exactly as before.
class ClassRange implements Iterator {
    private int $p = 0;
    public function current(): mixed { return $this->p; }
    public function key(): mixed { return $this->p; }
    public function next(): void { $this->p++; }
    public function rewind(): void { $this->p = 0; }
    public function valid(): bool { return $this->p < 2; }
}
$vals = [];
foreach (new ClassRange() as $v) { $vals[] = $v; }
var_dump($vals === [0, 1]);

?>
--EXPECT--
0 => 0
1 => 10
2 => 20
bool(true)
int(0)
int(30)
int(0)
bool(true)
bool(false)
bool(true)
bool(true)
