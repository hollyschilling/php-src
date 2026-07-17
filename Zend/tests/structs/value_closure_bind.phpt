--TEST--
Structs: Closure::bind/bindTo to a struct gets method (by-value $this) semantics
--FILE--
<?php

struct Point {
    public function __construct(public int $x, public int $y) {}
}

// A bound closure's $this is a coherent value within the invocation: a write is
// visible to a later read in the same body, exactly as in a method.
$obj = new Point(1, 0);
$c = Closure::bind(function() {
    $this->x = 9;
    return $this->x;
}, $obj, Point::class);
var_dump($c());        // 9: the read sees the write
var_dump($obj->x);     // 1: the mutation does not escape to the caller's value

// Each invocation operates on a fresh copy of the captured receiver.
$counter = Closure::bind(function() {
    $this->x++;
    return $this->x;
}, new Point(10, 0), Point::class);
var_dump($counter(), $counter(), $counter());   // 11, 11, 11 -- no state leaks across calls

// bindTo, compound assignment, and a dependent read all compose.
$f = (function() {
    $this->x += 5;
    $this->y = $this->x * 2;
    return [$this->x, $this->y];
})->bindTo(new Point(1, 0), Point::class);
var_dump($f());
var_dump($f());        // identical: the captured value is unchanged

?>
--EXPECT--
int(9)
int(1)
int(11)
int(11)
int(11)
array(2) {
  [0]=>
  int(6)
  [1]=>
  int(12)
}
array(2) {
  [0]=>
  int(6)
  [1]=>
  int(12)
}
