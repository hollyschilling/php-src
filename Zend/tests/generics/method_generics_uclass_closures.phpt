--TEST--
Generic methods: U::class, new U, and closures over method type parameters (signature and body)
--FILE--
<?php
declare(strict_types=1);

class Vec<T> { public function __construct(public array $i = []) {} }
class Price {} class Order {}

class Q<T> {
    public function name<U>(): string { return U::class; }
    public function make<U>(): object { return new U(); }
    public function mapper<U>(): callable {
        return function (U $x): U { return $x; };
    }
    public function factory<U>(): callable {
        return fn(): object => new Vec<U>([U::class, T::class]);
    }
}

$q = new Q<DateTime>();
var_dump($q->name<Price>());
var_dump(get_class($q->make<Order>()));

// Closure signatures substitute U per creating instantiation.
$m = $q->mapper<Price>();
var_dump(get_class($m(new Price())));
try { $m(new Order()); } catch (TypeError $e) { echo "sig enforced\n"; }
$m2 = $q->mapper<Order>();
var_dump(get_class($m2(new Order())));
try { $m2(new Price()); } catch (TypeError $e) { echo "sig enforced 2\n"; }

// Closure BODIES resolve U (and class-space T) through the adopted binding,
// including inside array literals (const folding must not swallow them).
$f = $q->factory<Price>();
$v = $f();
var_dump(get_class($v), $v->i);
?>
--EXPECT--
string(5) "Price"
string(5) "Order"
string(5) "Price"
sig enforced
string(5) "Order"
sig enforced 2
string(10) "Vec<Price>"
array(2) {
  [0]=>
  string(5) "Price"
  [1]=>
  string(8) "DateTime"
}
