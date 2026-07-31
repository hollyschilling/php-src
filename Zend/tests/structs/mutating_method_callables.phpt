--TEST--
Structs: mutating methods are not reachable through callables
--FILE--
<?php

struct Counter {
    public function __construct(public int $n = 0) {}
    public function inc() mutating: void { $this->n++; }
}

$c = new Counter();

try { $f = $c->inc(...); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { call_user_func([$c, 'inc']); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
try { Closure::fromCallable([$c, 'inc']); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
try { $cb = [$c, 'inc']; $cb(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { array_map([$c, 'inc'], [1]); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump(is_callable([$c, 'inc']));

// Reflection: the receiver argument itself shares the instance, so the
// exclusive-receiver gate rejects the call.
try { (new ReflectionMethod(Counter::class, 'inc'))->invoke($c); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Nothing above mutated the receiver.
var_dump($c->n);

// Non-mutating methods on the same struct remain fully callable.
struct Reader {
    public function __construct(public int $v = 3) {}
    public function get(): int { return $this->v; }
}
$r = new Reader();
$g = $r->get(...);
var_dump($g(), call_user_func([$r, 'get']));

?>
--EXPECT--
Cannot create a first-class callable of mutating method Counter::inc()
call_user_func(): Argument #1 ($callback) must be a valid callback, cannot call mutating method Counter::inc() through a callable
Failed to create closure from callable: cannot call mutating method Counter::inc() through a callable
Cannot call mutating method Counter::inc() through a callable
array_map(): Argument #1 ($callback) must be a valid callback or null, cannot call mutating method Counter::inc() through a callable
bool(false)
Cannot call mutating method Counter::inc() on a shared instance
int(0)
int(3)
int(3)
