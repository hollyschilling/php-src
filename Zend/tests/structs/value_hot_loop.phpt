--TEST--
Structs: value semantics hold in a hot loop (JIT-sensitive)
--DESCRIPTION--
Exercises copy-on-write separation on the ASSIGN_OBJ write path in a tight loop.
Pinned to the interpreter: the JIT currently inlines the object-property store
without separating a shared value-class instance, so a JIT-forcing variant is
deferred until the JIT value-class guard lands.
--INI--
opcache.jit=disable
--FILE--
<?php

struct Point { public function __construct(public int $x, public int $y) {} }

function bump(Point $p): int {
    $p->x = 999;      // by-value parameter: must not affect the caller
    return $p->x;
}

$orig = new Point(1, 2);
$bad = 0;
for ($i = 0; $i < 200000; $i++) {
    bump($orig);
    if ($orig->x !== 1) { $bad++; }

    $c = $orig;
    $c->x = $i;       // copy-on-assign in a hot loop
    if ($orig->x !== 1) { $bad++; }
}

var_dump($orig->x, $bad);

?>
--EXPECT--
int(1)
int(0)
