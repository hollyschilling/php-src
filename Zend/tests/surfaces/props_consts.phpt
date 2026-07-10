--TEST--
Surfaces: properties and class constants, set-visibility composition
--FILE--
<?php
class Counter {
    surface Ops;

    surface[Ops] int $weight = 10;
    surface[Ops] const LIMIT = 5;
    surface[Ops] private(set) int $sealed = 1;
}

$c = new Counter();
try { $x = $c->weight; } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $c->weight = 3; } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $x = Counter::LIMIT; } catch (Error $e) { echo $e->getMessage(), "\n"; }

function ops(Counter $c): void {
    use Counter with surface[Ops];
    echo "weight={$c->weight} limit=", Counter::LIMIT, "\n";
    $c->weight = 42;
    echo "written={$c->weight}\n";
    echo "sealed-read={$c->sealed}\n";
    try { $c->sealed = 9; } catch (Error $e) { echo "sealed-write: ", $e->getMessage(), "\n"; }
}
ops($c);
?>
--EXPECTF--
Cannot access surface property Counter::$weight (grant it with "use Counter with surface[...]")
Cannot access surface property Counter::$weight (grant it with "use Counter with surface[...]")
Cannot access surface constant Counter::LIMIT (grant it with "use Counter with surface[...]")
weight=10 limit=5
written=42
sealed-read=1
sealed-write: Cannot modify private(set) property Counter::$sealed from %s
