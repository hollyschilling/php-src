--TEST--
Generics: trait adaptations (insteadof / as) accept generic trait references
--FILE--
<?php
trait A<T> { public function get(): string { return "A"; } public function put(T $x): void {} }
trait B<T> { public function get(): string { return "B"; } }

class C {
    use A<int>, B<DateTime> {
        A<int>::get insteadof B<DateTime>;
        B<DateTime>::get as getB;
    }
}

$c = new C();
echo $c->get(), $c->getB(), "\n";
try { $c->put("x"); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
AB
C::put(): Argument #1 ($x) must be of type int, string given, called in %s on line %d
