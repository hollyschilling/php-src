--TEST--
Surfaces: an interface cannot be spanned across surfaces
--FILE--
<?php
interface I {
    public function a(): void;
    public function b(): void;
}
class Q {
    surface S1 implements I;
    surface S2;
    surface[S1] function a(): void {}
    surface[S2] function b(): void {}
}
?>
--EXPECTF--
Fatal error: Method Q::b() satisfies interface I bound to surface S1 but is on a different surface (an interface cannot be spanned across surfaces) in %s on line %d
