--TEST--
Ambiguous generic-vs-comparison shapes on method receivers are loud compile errors
--FILE--
<?php
const A = 1, B = 2;
class O { public $m = 5; public $count = 3; const LIMIT = 9; }
$o = new O;

// Preserved: ordinary comparisons on receivers never enter the rule
var_dump($o->count < 10);
var_dump($o->m < A);
var_dump($o->m < O::LIMIT);

// Preserved: bare-name shapes keep their PHP 8 meaning (no generic reading exists)
function f($a, $b) { return $a && $b; }
var_dump(f(A < B, B > (1)));

// Poisoned: type-argument shape on a method receiver with a comma or DNF
// before '(' — both readings exist, so neither wins silently.
$cases = [
    '$r = $o->m<A, B>(1);',
    '$r = $o->m<A|B>(1);',
    '$r = $o->m < A , B > (1);',      // whitespace never changes the outcome
    '$r = $o?->m<A, B>(1);',
    '$r = O::CONSTX<A, B>(1);',
    '$r = $o->a->b<A, B>(1);',        // chained receivers
];
foreach ($cases as $src) {
    try {
        eval($src);
        echo "no error\n";
    } catch (ParseError $e) {
        echo str_starts_with($e->getMessage(), 'Ambiguous mix') ? "poisoned\n" : "other: {$e->getMessage()}\n";
    } catch (Throwable $e) {
        echo "runtime: ", get_class($e), "\n";
    }
}

// self:: / static:: receivers poison identically
class P {
    public static function probe(): void {
        try { eval('$r = self::CONSTX<A, B>(1);'); echo "no error\n"; }
        catch (ParseError $e) { echo str_starts_with($e->getMessage(), 'Ambiguous mix') ? "poisoned\n" : "other\n"; }
        try { eval('$r = static::CONSTX<A|B>(1);'); echo "no error\n"; }
        catch (ParseError $e) { echo str_starts_with($e->getMessage(), 'Ambiguous mix') ? "poisoned\n" : "other\n"; }
    }
}
P::probe();

// A comment between the member name and '<' breaks receiver detection:
// the shape DECLINES (comparison reading, loud elsewhere) rather than
// poisoning — token-shape rule, documented edge.
try { eval('$r = $o->m/*c*/<A, B>(1);'); echo "no error\n"; }
catch (ParseError $e) { echo str_starts_with($e->getMessage(), 'Ambiguous mix') ? "poisoned\n" : "declined (other parse error)\n"; }

// Recovery spelling 1: parentheses keep the comparisons
var_dump(($o->m < A) | (B > (1)));

// Non-poisoned neighbors: literals and variables reject the shape outright,
// so these stay silent comparisons exactly as in PHP 8
var_dump($o->m < 4);
$x = 2;
var_dump($o->m < $x);
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(true)
poisoned
poisoned
poisoned
poisoned
poisoned
poisoned
poisoned
poisoned
declined (other parse error)
int(1)
bool(false)
bool(false)
