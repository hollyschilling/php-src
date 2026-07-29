--TEST--
Composite (DNF) type arguments: unions, intersections, array, null, canonical identity
--FILE--
<?php declare(strict_types=1);
interface B {} interface C {}
class D implements B, C {}
class A {}
class Vec<T> {
    public ?T $last = null;
    public function push(T $x): void { $this->last = $x; }
    public function tname(): string { return T::class; }
}

// scalar union: both members accepted, others rejected
$v = new Vec<int|string>();
$v->push(1);
$v->push("two");
try { $v->push(3.5); } catch (TypeError $e) { echo "float rejected\n"; }
var_dump($v::class);

// canonical identity: member order and case converge to one instantiation
var_dump(Vec<string|int>::class === Vec<int|string>::class);
var_dump((new Vec<STRING|InT>())::class);

// array as a type argument
$a = new Vec<array>();
$a->push([1, 2]);
try { $a->push("no"); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump($a::class);

// class|null, and '?' sugar canonicalizing to the same instantiation
$n = new Vec<A|null>();
$n->push(new A);
$n->push(null);
var_dump($n::class);
var_dump((new Vec<?A>())::class === $n::class);

// intersection
$i = new Vec<B&C>();
$i->push(new D);
try { $i->push(new A); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump($i::class);

// full DNF
$d = new Vec<A|(B&C)>();
$d->push(new A);
$d->push(new D);
var_dump($d::class);

// T::class renders the full composite (engine mask order for scalars)
var_dump($v->tname());
var_dump($i->tname());

// new T with a composite argument is an Error
class Maker<T> { public function make(): object { return new T(); } }
try { (new Maker<A|null>())->make(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { (new Maker<int|string>())->make(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
float rejected
string(15) "Vec<int|string>"
bool(true)
string(15) "Vec<int|string>"
Vec<array>::push(): Argument #1 ($x) must be of type array, string given, called in %s on line %d
string(10) "Vec<array>"
string(11) "Vec<A|null>"
bool(true)
Vec<B&C>::push(): Argument #1 ($x) must be of type B&C, A given, called in %s on line %d
string(8) "Vec<B&C>"
string(12) "Vec<(B&C)|A>"
string(10) "string|int"
string(3) "B&C"
Cannot use composite type argument ?A as a class
Cannot use scalar type argument string|int as a class
