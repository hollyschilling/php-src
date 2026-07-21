--TEST--
Structs: writes through multi-level struct nesting persist on the writing copy only
--FILE--
<?php

struct P3 { public function __construct(public int $x) {} public function bump(): void { $this->x++; } }
struct P2 { public function __construct(public P3 $c) {} }
struct P1 { public function __construct(public P2 $b) {} }
struct Bag { public function __construct(public array $items) {} }

// Three-level write: persists at every level of the writing copy, the
// original is untouched at every level.
$a = new P1(new P2(new P3(1)));
$b = $a;
$b->b->c->x = 42;
var_dump($a->b->c->x, $b->b->c->x);

// Sequential writes land on the already-separated chain and accumulate.
$b->b->c->x = 43;
$b->b->c->x += 7;
++$b->b->c->x;
var_dump($a->b->c->x, $b->b->c->x);

// Diamond: two copies of one original separate independently.
$c = $a;
$d = $a;
$c->b->c->x = 2;
$d->b->c->x = 3;
var_dump($a->b->c->x, $c->b->c->x, $d->b->c->x);

// Replacing a whole intermediate level after a deep write.
$e = $a;
$e->b->c->x = 5;
$e->b = new P2(new P3(9));
var_dump($a->b->c->x, $e->b->c->x);

// Struct inside an array inside a struct: the array CoW and the struct CoW
// compose; sibling elements stay shared until written.
$g = new Bag([new P3(7), new P3(8)]);
$h = $g;
$h->items[0]->x = 70;
var_dump($g->items[0]->x, $h->items[0]->x, $h->items[1]->x);

// A method call on a nested receiver is a read: its mutation is discarded.
$a->b->c->bump();
var_dump($a->b->c->x);

?>
--EXPECT--
int(1)
int(42)
int(1)
int(51)
int(1)
int(2)
int(3)
int(1)
int(9)
int(7)
int(70)
int(8)
int(1)
