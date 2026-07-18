--TEST--
Structs: the constructor is a mutating call, reachable only through object creation
--FILE--
<?php

struct P {
    public function __construct(public int $x) {}
    public function reinit(int $v): void { $this->__construct($v); }
}

$s = new P(1);
$t = $s;

/* The constructor initializes its receiver in place under the
 * borrowed-exclusive $this convention, which only object creation
 * establishes. Explicit invocation is a mutating call -- deferred scope --
 * so every route errors uniformly instead of the receiver-dependent
 * discard/throw/mutate behaviors it would otherwise produce. */
try { $s->__construct(2); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $s->reinit(5); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { call_user_func([$s, '__construct'], 3); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { (new ReflectionMethod(P::class, '__construct'))->invoke($s, 9); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $f = $s->__construct(...); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { Closure::fromCallable([$s, '__construct']); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $f = [$s, '__construct']; $f(3); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// A struct constructor is not callable, and no attempt above mutated anything.
var_dump(is_callable([$s, '__construct']));
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
Cannot call the constructor of struct P explicitly
Cannot call the constructor of struct P explicitly
call_user_func(): Argument #1 ($callback) must be a valid callback, cannot call the constructor of struct P explicitly
Cannot call the constructor of struct P explicitly
Cannot call the constructor of struct P explicitly
Failed to create closure from callable: cannot call the constructor of struct P explicitly
Cannot call the constructor of struct P explicitly
bool(false)
int(1)
int(1)
int(42)
int(7)
int(8)
int(9)
