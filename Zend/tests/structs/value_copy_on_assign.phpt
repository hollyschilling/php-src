--TEST--
Structs: assignment, argument passing and return all copy
--FILE--
<?php

struct Point {
    public function __construct(public float $x, public float $y) {}
}

// Assignment copies.
$a = new Point(1.0, 2.0);
$b = $a;
$b->x = 5.0;
var_dump($a->x, $b->x);

// Argument passing is by value.
function widen(Point $p): void { $p->x = 99.0; }
widen($a);
var_dump($a->x);

// Return copies.
function make(): Point { return new Point(7.0, 8.0); }
$c = make();
$c->x = 0.0;
var_dump($c->x);

// Storing into an array copies.
$arr = [$a];
$d = $arr[0];
$d->x = 50.0;
var_dump($arr[0]->x, $d->x);

// Storing into a plain object's property copies out.
$o = new stdClass();
$o->p = $a;
$e = $o->p;
$e->x = 3.0;
var_dump($o->p->x, $e->x);

?>
--EXPECT--
float(1)
float(5)
float(1)
float(0)
float(1)
float(50)
float(1)
float(3)
