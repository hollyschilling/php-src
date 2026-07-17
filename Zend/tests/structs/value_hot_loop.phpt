--TEST--
Structs: value semantics hold in a hot loop (JIT-sensitive)
--DESCRIPTION--
Exercises copy-on-write separation on the ASSIGN_OBJ write path in a tight
loop, under whatever engine the test run selects (interpreter or JIT). The
JIT-forcing variants live in value_jit_tracing.phpt / value_jit_function.phpt.
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
