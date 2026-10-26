--TEST--
Structs: the constructor is a named mutating method; explicit calls follow the mutating receiver rules
--FILE--
<?php

struct P {
    public function __construct(public int $x) {}
    public function reinit(int $v): void { $this->__construct($v); }
}

$s = new P(1);
$t = $s;

/* The constructor is the built-in mutating method: on a variable receiver an
 * explicit call separates the caller's slot (copy-on-write) and re-initializes
 * in place, exactly like any `mutating` method. Routes that cannot lend a
 * writable receiver -- callables, first-class callables, $this in a
 * non-mutating method, a shared receiver through reflection -- error. */

// Variable receiver: legal. $s was shared with $t, so it separates first.
$s->__construct(2);
var_dump($s->x, $t->x);

// $this in a non-mutating method is not a lendable receiver.
try { $s->reinit(5); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Callable routes cannot lend a receiver slot.
try { call_user_func([$s, '__construct'], 3); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $f = $s->__construct(...); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { Closure::fromCallable([$s, '__construct']); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $f = [$s, '__construct']; $f(3); } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump(is_callable([$s, '__construct']));

// Reflection: passing the receiver as an argument shares it, so the
// exclusive-receiver gate rejects the call.
try { (new ReflectionMethod(P::class, '__construct'))->invoke($s, 9); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// No banned attempt above mutated anything.
var_dump($s->x, $t->x);

// Object-creation routes are unaffected.
var_dump((new P(42))->x);
var_dump((new ReflectionClass(P::class))->newInstance(7)->x);
var_dump((new ReflectionClass(P::class))->newInstanceArgs([8])->x);

// Classes keep explicit constructor calls.
class C { public function __construct(public int $x = 0) {} }
$c = new C(1);
$c->__construct(9);
var_dump($c->x);

?>
--EXPECT--
int(2)
int(1)
Cannot call mutating method P::__construct() on $this in a non-mutating method
call_user_func(): Argument #1 ($callback) must be a valid callback, cannot call the constructor of struct P explicitly
Cannot create a first-class callable of mutating method P::__construct()
Failed to create closure from callable: cannot call the constructor of struct P explicitly
Cannot call mutating method P::__construct() through a callable
bool(false)
Cannot call the constructor of struct P explicitly
int(2)
int(1)
int(42)
int(7)
int(8)
int(9)
