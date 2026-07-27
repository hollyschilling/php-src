--TEST--
Structs: C-level invocation routes give by-value receivers a fresh copy per call
--FILE--
<?php

struct P {
    public function __construct(public int $x) {}
    public function bump(): int { $this->x++; return $this->x; }
}

// A bound closure invoked through zend_call_function (array_map, usort, ...)
// must behave exactly like a direct $c() call: each invocation operates on a
// fresh copy of the capture, even when the closure is the sole holder.
$c = Closure::bind(function ($_) { $this->x++; return $this->x; }, new P(0), P::class);
var_dump(array_map($c, [null])[0]);
var_dump(array_map($c, [null])[0]);
var_dump($c(null));

// Struct-method callables through C routes: mutations stay local to the call.
$cmp = new P(0);
$arr = [3, 1, 2];
usort($arr, function ($a, $b) use ($cmp) { $cmp->bump(); return $a <=> $b; });
var_dump($arr[0], $cmp->x);

// ReflectionMethod::invoke on a writing method: by-value receiver.
$p = new P(1);
var_dump((new ReflectionMethod(P::class, 'bump'))->invoke($p), $p->x);

// A generator factory bound to a struct, created through a C route: each
// generator advances its own copy; the capture is never mutated.
$g = Closure::bind(function ($_) { $this->x++; yield $this->x; $this->x++; yield $this->x; }, new P(10), P::class);
var_dump(iterator_to_array(array_map($g, [null])[0]));
var_dump(iterator_to_array(array_map($g, [null])[0]));

?>
--EXPECT--
int(1)
int(1)
int(1)
int(1)
int(0)
int(2)
int(1)
array(2) {
  [0]=>
  int(11)
  [1]=>
  int(12)
}
array(2) {
  [0]=>
  int(11)
  [1]=>
  int(12)
}
