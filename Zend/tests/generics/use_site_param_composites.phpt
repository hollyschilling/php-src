--TEST--
Generics: param-dependent composite references (C<T>) in template signatures, properties and bodies
--FILE--
<?php
declare(strict_types=1);

// Self-referential signature positions.
class C<T> {
    public function __construct(public array $items = []) {}
    public function f(C<T> $p): C<T> { return $p; }
    public function merge(C<T> $other): C<T> {
        return new C<T>([...$this->items, ...$other->items]);
    }
}
class Bag {}

$a = new C<Bag>([new Bag()]);
$b = new C<Bag>([new Bag(), new Bag()]);
var_dump(get_class($a->f($b)));
$m = $a->merge($b);
var_dump(get_class($m), count($m->items));

try {
    $a->f(new C<int>());
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

// Other templates, property types, mixed concrete arguments.
class Vec<T> {
    public array $i = [];
    public function push(T $v): void { $this->i[] = $v; }
    public static function make(): static { return new static(); }
    const LABEL = "vec";
}
class M<T> {
    public ?Vec<T> $items = null;
    public function fill(Vec<T> $src): Vec<T> { $this->items = $src; return $src; }
    public function fresh(): Vec<T> { return new Vec<T>(); }
    public function viaStatic(): object { return Vec<T>::make(); }
    public function viaConst(): string { return Vec<T>::LABEL; }
    public function is(object $o): bool { return $o instanceof Vec<T>; }
}
class Pair<K, V> {}
class N<T> { public function g(Pair<string, T> $p): void {} }

$m2 = new M<Bag>();
var_dump(get_class($m2->fresh()));
var_dump(get_class($m2->viaStatic()));
var_dump($m2->viaConst());
$m2->items = new Vec<Bag>();
try {
    $m2->items = new Vec<int>();
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

// instanceof resolves per binding (shared opcodes, per-clone caches).
class Foo {}
$mf = new M<Foo>();
var_dump($m2->is(new Vec<Bag>()), $m2->is(new Vec<Foo>()));
var_dump($mf->is(new Vec<Foo>()), $mf->is(new Vec<Bag>()));

// new C<T> per binding through the same shared opcode.
var_dump(get_class($mf->fresh()));

(new N<Bag>)->g(new Pair<string, Bag>());
try {
    (new N<Bag>)->g(new Pair<string, int>());
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

// Closures declared in template methods substitute composites too.
class W<T> {
    public function mk(): callable {
        return function (Vec<T> $v): Vec<T> { return $v; };
    }
}
$f = (new W<Bag>)->mk();
var_dump(get_class($f(new Vec<Bag>())));
try {
    $f(new Vec<int>());
} catch (TypeError $e) {
    echo "closure enforced\n";
}

?>
--EXPECTF--
string(6) "C<Bag>"
string(6) "C<Bag>"
int(3)
C<Bag>::f(): Argument #1 ($p) must be of type C<Bag>, C<int> given, called in %s on line %d
string(8) "Vec<Bag>"
string(8) "Vec<Bag>"
string(3) "vec"
Cannot assign Vec<int> to property M<Bag>::$items of type ?Vec<Bag>
bool(true)
bool(false)
bool(true)
bool(false)
string(8) "Vec<Foo>"
N<Bag>::g(): Argument #1 ($p) must be of type Pair<string,Bag>, Pair<string,int> given, called in %s on line %d
string(8) "Vec<Bag>"
closure enforced
