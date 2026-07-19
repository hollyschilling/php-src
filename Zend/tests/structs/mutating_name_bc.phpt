--TEST--
`mutating` reserves nothing: functions, classes, methods, constants, and types keep the name
--FILE--
<?php

function mutating(int $n): int { return $n + 1; }
var_dump(mutating(41));

class mutating {
    const mutating = 'k';
    public function mutating(): int { return 7; }
    public ?mutating $next = null;
}
$m = new mutating();
var_dump($m->mutating());
var_dump(mutating::mutating);
var_dump($m instanceof mutating, $m->next);

$f = 'mutating';
var_dump($f(1));

// And a struct method named mutating can itself be mutating.
struct S {
    public int $n = 0;
    public function mutating(int $k) mutating: void { $this->n += $k; }
}
$s = new S();
$s->mutating(5);
var_dump($s->n);

?>
--EXPECT--
int(42)
int(7)
string(1) "k"
bool(true)
NULL
int(2)
int(5)
