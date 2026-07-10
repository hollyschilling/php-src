--TEST--
Surfaces: in-body grants, arrow functions inherit, long closures do not, file-level position
--FILE--
<?php
class Q {
    surface S;
    surface[S] function poke(): string { return "poked"; }
}

// Declared before any grant: sees no file-level grant set.
function before(Q $q): string {
    try { return $q->poke(); } catch (Error $e) { return "denied-before"; }
}

function inBody(Q $q): void {
    use Q with surface[S];
    echo $q->poke(), "\n";

    $arrow = fn() => $q->poke();
    echo "arrow: ", $arrow(), "\n";

    $closure = function () use ($q) {
        try { return $q->poke(); } catch (Error $e) { return "denied-closure"; }
    };
    echo "closure: ", $closure(), "\n";
}

$q = new Q();
echo before($q), "\n";
inBody($q);

use Q with surface[S];

// Declared after the grant: covered.
function after(Q $q): string {
    return $q->poke();
}
echo "after: ", after($q), "\n";
echo "before again: ", before($q), "\n";
?>
--EXPECT--
denied-before
poked
arrow: poked
closure: denied-closure
after: poked
before again: denied-before
