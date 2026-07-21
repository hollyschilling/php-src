--TEST--
Structs: value semantics hold in hot loops under the function JIT
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=function
opcache.jit_buffer_size=64M
opcache.jit_hot_func=1
--FILE--
<?php

struct Point {
    public function __construct(public int $x, public int $y) {}
    public function movedX(int $dx): Point { $this->x += $dx; return $this; }
}
struct Rect { public function __construct(public Point $min, public Point $max) {} }

$bad = 0;

function loop_all(): int {
    $bad = 0;

    $orig = new Point(1, 2);
    for ($i = 0; $i < 50000; $i++) {
        $c = $orig; $c->x = $i;
        if ($orig->x !== 1) { $bad |= 1; break; }
    }

    $p0 = new Point(10, 0);
    for ($i = 0; $i < 50000; $i++) {
        $q = $p0->movedX(1);
        if ($p0->x !== 10 || $q->x !== 11) { $bad |= 2; break; }
    }

    $r1 = new Rect(new Point(0,0), new Point(1,1));
    for ($i = 0; $i < 50000; $i++) {
        $r2 = $r1;
        $r2->max->x = $i;
        if ($r1->max->x !== 1) { $bad |= 4; break; }
    }

    $s0 = new Point(5, 5);
    for ($i = 0; $i < 50000; $i++) {
        $t = $s0; $t->x += 3; $t->y++;
        if ($s0->x !== 5 || $t->x !== 8 || $t->y !== 6) { $bad |= 8; break; }
    }

    for ($i = 0; $i < 50000; $i++) {
        $n = new Point($i, $i + 1);
        if ($n->x !== $i) { $bad |= 16; break; }
    }

    return $bad;
}

var_dump(loop_all());

?>
--EXPECT--
int(0)
