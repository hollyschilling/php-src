--TEST--
Structs: value semantics hold in hot loops under the tracing JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=64M
opcache.jit_hot_loop=64
opcache.jit_hot_func=127
--FILE--
<?php

struct Point {
    public function __construct(public int $x, public int $y) {}
    public function movedX(int $dx): Point { $this->x += $dx; return $this; }
    public function bump(): int { $this->x++; return $this->x; }
}
struct Rect { public function __construct(public Point $min, public Point $max) {} }

$bad = 0;

// Copy-on-assign and by-value parameters (ASSIGN_OBJ on a CV).
function bump_param(Point $p): int { $p->x = 999; return $p->x; }
$orig = new Point(1, 2);
for ($i = 0; $i < 200000; $i++) {
    bump_param($orig);
    if ($orig->x !== 1) { $bad |= 1; break; }
    $c = $orig; $c->x = $i;
    if ($orig->x !== 1) { $bad |= 2; break; }
}

// Method wither: $this writes are local, return $this returns the copy.
$p0 = new Point(10, 0);
for ($i = 0; $i < 200000; $i++) {
    $q = $p0->movedX(1);
    if ($p0->x !== 10 || $q->x !== 11) { $bad |= 4; break; }
}

// Nested writes separate every level (FETCH_OBJ_W chain).
$r1 = new Rect(new Point(0,0), new Point(1,1));
for ($i = 0; $i < 200000; $i++) {
    $r2 = $r1;
    $r2->max->x = $i;
    if ($r1->max->x !== 1) { $bad |= 8; break; }
}

// Compound assignment and increment/decrement.
$s0 = new Point(5, 5);
for ($i = 0; $i < 200000; $i++) {
    $t = $s0; $t->x += 3; $t->y++;
    if ($s0->x !== 5 || $s0->y !== 5 || $t->x !== 8 || $t->y !== 6) { $bad |= 16; break; }
}

// Constructor: borrowed exclusive $this, escape-checked at JIT'd leave.
for ($i = 0; $i < 200000; $i++) {
    $n = new Point($i, $i + 1);
    if ($n->x !== $i) { $bad |= 32; break; }
}

// Bound closure: fresh copy of the capture per invocation.
$cl = Closure::bind(function() { $this->x++; return $this->x; }, new Point(10, 0), Point::class);
for ($i = 0; $i < 200000; $i++) {
    if ($cl() !== 11) { $bad |= 64; break; }
}

// First-class callable as sole holder of its capture.
$fobj = new Point(1, 0);
$f = $fobj->bump(...);
unset($fobj);
for ($i = 0; $i < 200000; $i++) {
    if ($f() !== 2) { $bad |= 128; break; }
}

var_dump($bad);

?>
--EXPECT--
int(0)
