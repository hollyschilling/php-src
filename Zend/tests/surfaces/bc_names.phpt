--TEST--
Surfaces: "surface" stays usable as class, method, constant, and function name
--FILE--
<?php
class surface {}
$s = new surface();
echo get_class($s), "\n";

class A {
    const surface = 2;
    public function surface(): int { return 1; }
    public surface $typed;
}
$a = new A();
$a->typed = $s;
echo $a->surface() + A::surface, "\n";
echo get_class($a->typed), "\n";

function surface(): int { return 7; }
echo surface(), "\n";
?>
--EXPECT--
surface
3
surface
7
