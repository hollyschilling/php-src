--TEST--
Structs: traits may declare mutating methods for struct consumers; the color travels
--FILE--
<?php

trait Bumps {
    public function bump() mutating: void { $this->n++; }
}

struct Counter {
    public int $n = 0;
    use Bumps;
}

$c = new Counter();
$c->bump();
$c->bump();
var_dump($c->n);

// The flattened method keeps its mutating color: callable routes reject it.
try { $f = $c->bump(...); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Shared receivers separate.
$d = $c;
$d->bump();
var_dump($c->n, $d->n);

?>
--EXPECT--
int(2)
Cannot create a first-class callable of mutating method Counter::bump()
int(2)
int(3)
