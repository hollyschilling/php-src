--TEST--
Structs: $this from a bound closure passes to callees as an object, not an INDIRECT
--FILE--
<?php

struct P {
    public function __construct(public int $x) {}
}

// A scopeless closure compiles $this through FETCH_THIS. Only property-write
// containers may receive an INDIRECT into the This slot; argument sends must
// get an ordinary handle copy -- their consumers never dereference INDIRECT.

// Dynamic callee (SEND_VAR_EX).
$cb = function ($p) { return is_object($p) ? get_class($p) . ':' . $p->x : 'BROKEN:' . gettype($p); };
$c = Closure::bind(function () use ($cb) { return $cb($this); }, new P(7), P::class);
var_dump($c());

// Known callee.
function probe($p) { return is_object($p) ? get_class($p) . ':' . $p->x : 'BROKEN'; }
$k = Closure::bind(function () { return probe($this); }, new P(3), P::class);
var_dump($k());

// By-reference parameter (SEND_REF): the callee gets a reference to the
// frame's copy; clobbering it must not corrupt $this or crash.
function mut(&$p) { $p = 'clobbered'; }
$m = Closure::bind(function () { mut($this); return is_object($this) ? 'intact:' . $this->x : 'BROKEN'; }, new P(5), P::class);
var_dump($m());

// Write-then-send: the send happens after a write separated the This slot;
// the callee must see the written copy as a plain object.
$w = Closure::bind(function () use ($cb) { $this->x = 42; return $cb($this); }, new P(1), P::class);
var_dump($w());

// Coherence within and across invocations is unchanged.
$c2 = Closure::bind(function () { $this->x++; return $this->x; }, new P(10), P::class);
var_dump($c2(), $c2());

?>
--EXPECT--
string(3) "P:7"
string(3) "P:3"
string(8) "intact:5"
string(4) "P:42"
int(11)
int(11)
