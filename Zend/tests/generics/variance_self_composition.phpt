--TEST--
Variance: self-references compose polarity; the collections-of-collections shape works
--FILE--
<?php declare(strict_types=1);
class Animal {} class Dog extends Animal {}

interface Seq<out T> {
    public function head(): ?T;
    public function chunk(): ?Seq<Seq<T>>;   // T at depth 2 through self: out∘out∘out = out
}
class Vec<T> implements Seq<T> {
    public function __construct(private array $i = []) {}
    public function head(): ?T { return $this->i[0] ?? null; }
    public function chunk(): ?Seq<Seq<T>> { return null; }
}
$v = new Vec<Dog>([new Dog]);
var_dump($v instanceof Seq<Animal>);
var_dump($v->chunk());

?>
--EXPECT--
bool(true)
NULL
