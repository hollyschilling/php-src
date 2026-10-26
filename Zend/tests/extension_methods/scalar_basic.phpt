--TEST--
Scalar extension methods: string/int/float/bool/array targets, receiver by value
--FILE--
<?php
extension string $s {
    function length(): int { return strlen($s); }
    function shout(): string { return strtoupper($s) . "!"; }
    function twice(): string { return $s->shout() . $s->shout(); }
}
extension int $n {
    function clamp(int $min, int $max): int { return max($min, min($max, $n)); }
}
extension float $f {
    function halved(): float { return $f / 2; }
}
extension bool $b {
    function label(): string { return $b ? "yes" : "no"; }
}
extension array $a {
    function total(): int|float { return array_sum($a); }
}

$s = "hello";
var_dump($s->length());
var_dump("abc"->shout());              // literal receiver
var_dump($s->twice());                 // chained scalar extension calls
var_dump((5 + 12)->clamp(0, 10));      // TMP receiver
var_dump((3.5)->halved());
var_dump(true->label(), false->label());
var_dump([1, 2, 3.5]->total());
$m = "len" . "gth";
var_dump($s->$m());                    // dynamic method name
var_dump($s);                          // receiver unchanged: value semantics

try { $s->missing(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { (3.5)->length(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump(null?->length());             // nullsafe short-circuits, no dispatch
?>
--EXPECT--
int(5)
string(4) "ABC!"
string(12) "HELLO!HELLO!"
int(10)
float(1.75)
string(3) "yes"
string(2) "no"
float(6.5)
int(5)
string(5) "hello"
Call to a member function missing() on string
Call to a member function length() on float
NULL
