--TEST--
Variance: deferred extends composes through the extended template's variance
--FILE--
<?php
interface ReadableSequence<out T> {
    public function get(int $i): T;
}
interface SortedSequence<out T> extends ReadableSequence<T> {
    public function first(): T;
}
class Animal {} class Cat extends Animal {}
class Cats implements SortedSequence<Cat> {
    public function get(int $i): Cat { return new Cat(); }
    public function first(): Cat { return new Cat(); }
}
function show(ReadableSequence<Animal> $r): string { return $r->get(0)::class; }
var_dump(show(new Cats())); // SortedSequence<Cat> -> ReadableSequence<Cat> -> <Animal>
var_dump(new Cats() instanceof SortedSequence<Animal>);
var_dump(new Cats() instanceof ReadableSequence<Animal>);
?>
--EXPECT--
string(3) "Cat"
bool(true)
bool(true)
