--TEST--
Variance: in/out interface parameters create subtype edges between instantiations
--FILE--
<?php declare(strict_types=1);
class Animal {} class Dog extends Animal {} class Cat extends Animal {}

interface Seq<out T> {
    public function head(): ?T;
    public const int SIZE = 0;
}
interface Sink<in T> {
    public function put(T $x): void;
}
class Vec<T> implements Seq<T>, Sink<T> {
    public function __construct(private array $i = []) {}
    public function head(): ?T { return $this->i[0] ?? null; }
    public function put(T $x): void { $this->i[] = $x; }
}

$dogs = new Vec<Dog>([new Dog]);
// covariance: Seq<Dog> <: Seq<Animal>, and only upward
var_dump($dogs instanceof Seq<Dog>);
var_dump($dogs instanceof Seq<Animal>);
var_dump($dogs instanceof Seq<Cat>);
// contravariance: Sink<Animal> <: Sink<Dog>, and only downward
$all = new Vec<Animal>();
var_dump($all instanceof Sink<Dog>);
var_dump($dogs instanceof Sink<Animal>);
// the invariance default is untouched: classes have no edges
var_dump($dogs instanceof Vec<Animal>);

// parameter and return type checks ride the same edges
function feed(Sink<Dog> $s): void { $s->put(new Dog); echo "fed\n"; }
feed($all);
function peek(Seq<Animal> $s): mixed { return $s->head(); }
var_dump(peek($dogs) instanceof Dog);

// composite arguments compose with edges: Seq<Dog|Cat> <: Seq<Animal>
$mixed = new Vec<Dog|Cat>([new Cat]);
var_dump($mixed instanceof Seq<Animal>);
var_dump($mixed instanceof Seq<Dog>);

// nested edges recurse: Seq<Vec<Dog>> is invariant in Vec (class), but
// Seq<Seq<Dog>>-shaped queries recurse through the same variance check
$nested = new Vec<Vec<Dog>>([$dogs]);
var_dump($nested instanceof Seq<Vec<Dog>>);
var_dump($nested instanceof Seq<Vec<Animal>>);   // Vec invariant: no edge

// wrong direction never sneaks through a TypeError boundary
try { feed(new Vec<Cat>()); } catch (TypeError $e) { echo "TypeError ok\n"; }
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
fed
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
TypeError ok
