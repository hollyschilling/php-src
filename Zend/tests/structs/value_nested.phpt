--TEST--
Structs: nested struct writes separate every level of the access path
--FILE--
<?php

struct Point { public function __construct(public float $x, public float $y) {} }
struct Rect { public function __construct(public Point $min, public Point $max) {} }

$r1 = new Rect(new Point(0.0, 0.0), new Point(1.0, 1.0));
$r2 = $r1;

// Writing a nested property separates $r2 and then $r2->max; $r1 is untouched.
$r2->max->x = 9.0;
var_dump($r1->max->x, $r2->max->x);

// Compound assignment and increment on nested properties.
$r3 = $r1;
$r3->max->x += 100.0;
$r3->min->y++;
var_dump($r1->max->x, $r3->max->x, $r1->min->y, $r3->min->y);

?>
--EXPECT--
float(1)
float(9)
float(1)
float(101)
float(0)
float(1)
