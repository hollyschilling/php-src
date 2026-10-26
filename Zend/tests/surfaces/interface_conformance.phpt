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
Fatal error: Method Q::b() satisfies interface I but is not on a surface bound to it (an interface-reachable member must be public or on a surface bound to that interface) in %s on line %d
