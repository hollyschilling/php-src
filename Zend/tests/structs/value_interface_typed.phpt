--TEST--
Structs: value semantics hold when a nesting level is typed as an interface
--FILE--
<?php

interface Boxed { public function get(): int; }

struct P3 { public function __construct(public int $x) {} }
struct SBox implements Boxed {
    public function __construct(public P3 $c) {}
    public function get(): int { return $this->c->x; }
}
class CBox implements Boxed {
    public function __construct(public P3 $c) {}
    public function get(): int { return $this->c->x; }
}
struct Outer { public function __construct(public Boxed $b) {} }

// Struct hidden behind an interface-typed property: the declared type is
// irrelevant to separation, which keys off the runtime instance. The chain
// separates level by level exactly as with concrete struct types.
$a = new Outer(new SBox(new P3(1)));
$b = $a;
$b->b->c->x = 42;
var_dump($a->b->c->x, $b->b->c->x);

// A CLASS behind the same interface-typed property keeps reference
// semantics: copying Outer shares the CBox handle (shallow copy), so the
// write is visible through both copies.
$c = new Outer(new CBox(new P3(5)));
$d = $c;
$d->b->c->x = 50;
var_dump($c->b->c->x, $d->b->c->x);

// One polymorphic write site, alternating struct and class receivers:
// the struct call leaves the caller's value untouched, the class call
// mutates the shared object -- the value/reference distinction is decided
// per instance, not per call site.
function poke(Boxed $box): int { $box->c->x = 999; return $box->c->x; }
$s = new SBox(new P3(1));
$k = new CBox(new P3(1));
var_dump(poke($s), $s->c->x);
var_dump(poke($k), $k->c->x);

// Interface-typed locals still copy on assignment.
$e = new SBox(new P3(7));
$f = $e;
$f->c->x = 70;
var_dump($e->c->x, $f->c->x);

// instanceof and interface method dispatch are unaffected.
var_dump($e instanceof Boxed, $e->get());

?>
--EXPECT--
int(1)
int(42)
int(50)
int(50)
int(999)
int(1)
int(999)
int(999)
int(7)
int(70)
bool(true)
int(7)
