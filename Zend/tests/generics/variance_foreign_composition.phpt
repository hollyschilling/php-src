--TEST--
Variance: variant parameters compose through foreign templates' declared variance
--FILE--
<?php
interface ReadableSequence<out T> {
    public function get(int $i): T;
}
interface WritableSequence<in T> {
    /* in T inside ReadableSequence's out slot: composed polarity is input. */
    public function append(ReadableSequence<T> $items): void;
}
class Animal {} class Cat extends Animal {}
class Store implements WritableSequence<Animal> {
    public array $log = [];
    public function append(ReadableSequence<Animal> $items): void { $this->log[] = $items; }
}
class CatList implements ReadableSequence<Cat> {
    public function get(int $i): Cat { return new Cat(); }
}
function feed(WritableSequence<Cat> $sink): void { $sink->append(new CatList()); }
$s = new Store();
feed($s); // WritableSequence<Animal> <: WritableSequence<Cat> via in T
var_dump($s->log[0]::class);
var_dump($s instanceof WritableSequence<Cat>);

/* in inside in composes to output: legal home for out T */
interface Transformer<out T> {
    public function reject(WritableSequence<T> $sink): void;
}
class CatSource implements Transformer<Cat> {
    public function reject(WritableSequence<Cat> $sink): void {}
}
var_dump(new CatSource() instanceof Transformer<Animal>);
?>
--EXPECT--
string(7) "CatList"
bool(true)
bool(true)
