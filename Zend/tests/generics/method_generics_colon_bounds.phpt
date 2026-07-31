--TEST--
Generic methods: ':' bounds with composite (DNF) types on method type parameters
--FILE--
<?php declare(strict_types=1);
class S implements Stringable { public function __toString(): string { return "s"; } }
class Plain {}

class Seq {
    public function keyBy<K: string|Stringable>(): string { return K::class; }
    public function pick<U: Countable&Traversable>(): string { return U::class; }
}
class CT implements Countable, IteratorAggregate {
    public function count(): int { return 0; }
    public function getIterator(): Iterator { return new ArrayIterator([]); }
}

$s = new Seq();
var_dump($s->keyBy<S>());
var_dump($s->keyBy<string>());
var_dump($s->keyBy::<S|string>());
try { $s->keyBy<Plain>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $s->keyBy<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

var_dump($s->pick<CT>());
try { $s->pick<Plain>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
string(1) "S"
string(6) "string"
string(8) "S|string"
Plain does not satisfy the bound string|Stringable of type parameter K on %s
int does not satisfy the bound string|Stringable of type parameter K on %s
string(2) "CT"
Plain does not satisfy the bound Countable&Traversable of type parameter U on %s