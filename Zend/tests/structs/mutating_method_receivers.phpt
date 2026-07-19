--TEST--
Structs: mutating calls require a lendable receiver slot
--FILE--
<?php

struct Counter {
    public function __construct(public int $n = 0) {}
    public function inc() mutating: void { $this->n++; }
    public function copy(): Counter { return $this; }
    public function tryIndirect(): void { $this->inc(); }
    public function tryIndirectStatic(): void { self::inc(); }
}

$c = new Counter();

// A temporary (call result, expression value) is not a slot.
try { $c->copy()->inc(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { (new Counter())->inc(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// $this inside a non-mutating method is bound by value: not lendable,
// through either call syntax.
try { $c->tryIndirect(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $c->tryIndirectStatic(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// A struct-typed property is not yet a lendable receiver (scoped-borrow
// receiver fetches are the next tier); today it reads as a temporary.
class Holder { public function __construct(public Counter $counter) {} }
$h = new Holder(new Counter(5));
try { $h->counter->inc(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump($h->counter->n);

// No banned attempt mutated anything.
var_dump($c->n);

?>
--EXPECT--
Cannot call mutating method Counter::inc() on a temporary value
Cannot call mutating method Counter::inc() on a temporary value
Cannot call mutating method Counter::inc() on $this in a non-mutating method
Cannot call mutating method Counter::inc() on $this in a non-mutating method
Cannot call mutating method Counter::inc() on a temporary value
int(5)
int(0)
