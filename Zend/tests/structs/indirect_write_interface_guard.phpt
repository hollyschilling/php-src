--TEST--
Structs: guarded interface-typed class property rejects nested struct writes by runtime instance
--FILE--
<?php

interface I { public function get(): int; }

struct S implements I {
    public function __construct(public int $v) {}
    public function get(): int { return $this->v; }
}
class K implements I {
    public function __construct(public int $v) {}
    public function get(): int { return $this->v; }
}
class C {
    public private(set) I $i;
    public function __construct(I $inner) { $this->i = $inner; }
    public function setInner(int $v): void { $this->i->v = $v; }
}

// The property's declared type (an interface) says nothing about value vs
// reference; the decision is made per runtime instance. A struct behind the
// guarded property is a value, so the nested write writes the property:
// without set access it must fail, and must not mutate.
$c = new C(new S(10));
try {
    $c->i->v = 5;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
var_dump($c->i->v);

// A class behind the same guarded property keeps interior mutability: the
// write mutates the referenced object, not the property slot.
$k = new C(new K(10));
$k->i->v = 5;
var_dump($k->i->v);

// Compound assignment, increment, and dynamic property names take the same
// error path for the struct.
try { $c->i->v += 1; } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $c->i->v++;    } catch (Error $e) { echo $e->getMessage(), "\n"; }
$n = 'i';
try { $c->{$n}->v = 1; } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump($c->i->v);

// In class scope set access is granted: the write separates and persists
// into the property as usual.
$c->setInner(77);
var_dump($c->i->v);

?>
--EXPECT--
Cannot indirectly modify private(set) property C::$i from global scope
int(10)
int(5)
Cannot indirectly modify private(set) property C::$i from global scope
Cannot indirectly modify private(set) property C::$i from global scope
Cannot indirectly modify private(set) property C::$i from global scope
int(10)
int(77)
