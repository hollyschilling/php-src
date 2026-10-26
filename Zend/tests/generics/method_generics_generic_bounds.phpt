--TEST--
Generic methods: generic bounds on method type parameters (U: Comparable<U>)
--FILE--
<?php declare(strict_types=1);
interface Comparable<in C> { public function compareTo(C $o): int; }
class Price implements Comparable<Price> {
    public function __construct(public int $cents = 0) {}
    public function compareTo(Price $o): int { return $this->cents <=> $o->cents; }
}
class Plain {}
class Box<T> { public function __construct() {} }

class Seq<T> {
    public function __construct() {}
    // method F-bound
    public function maxBy<U: Comparable<U>>(): string { return U::class; }
    // method bound referencing the ENCLOSING CLASS parameter
    public function wrap<U: Box<T>>(): string { return U::class; }
}

$s = new Seq<Price>();
var_dump($s->maxBy<Price>());
try { $s->maxBy<Plain>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $s->maxBy<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// class-space substitution: T = Price, so the bound is Box<Price>
var_dump($s->wrap<Box<Price>>());
try { $s->wrap<Box<Plain>>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
string(5) "Price"
Plain does not satisfy the bound Comparable<Plain> of type parameter U on Seq<Price>::maxBy()
int does not satisfy the bound Comparable<int> of type parameter U on Seq<Price>::maxBy()
string(10) "Box<Price>"
Box<Plain> does not satisfy the bound Box<Price> of type parameter U on Seq<Price>::wrap()
