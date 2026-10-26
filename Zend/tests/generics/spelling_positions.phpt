--TEST--
Generics spellings: '::<' at every generic-open position; committed bare '<' in single-token-committed contexts
--FILE--
<?php
declare(strict_types=1);

// ---- TurboFish at declaration sites ----
class D1::<X> { public ?X $v = null; }
interface D2::<A, B> {}
trait D3::<T> { public function t(): string { return "trait"; } }
class D4::<...Ts> {}
// Type-parameter lists attach to classes, interfaces and traits. Methods and
// functions do not take them here; method_params_unsupported.phpt pins that.

var_dump((new ReflectionClass('D1'))->isGenericTemplate());
var_dump((new ReflectionClass('D1'))->getGenericTypeParameters()[0]['name']);

// ---- TurboFish in type positions ----
class Vec<T> { public array $i = []; }
function g1(Vec::<int> $x): Vec::<int> { return $x; }
class H1 {
    public ?Vec::<int> $p = null;
    const ?Vec::<int> Q = null;
}
var_dump(get_class(g1(new Vec<int>())));
$h = new H1();
$h->p = new Vec<int>();
try { $h->p = new Vec<string>(); } catch (TypeError $e) { echo "prop enforced\n"; }

// ---- TurboFish in expression positions ----
$v = new Vec::<int>();
var_dump(get_class($v));
var_dump($v instanceof Vec::<int>);
var_dump(Vec::<int>::class);
var_dump(get_class(new D1::<Vec<int>>()));

// ---- TurboFish extends / implements / trait use / adaptations ----
class E1 extends Vec::<int> {}
interface I1<T> {}
class E2 implements I1::<int> {}
class U1 { use D3::<int>; }
trait TB { public function t(): string { return "b"; } }
class U2 { use D3<int>, TB { D3::<int>::t insteadof TB; } }
var_dump(get_parent_class(new E1()));
var_dump((new U2)->t());

// ---- Attributes: both spellings ----
#[Attribute] class At<T> {}
#[At<int>] class TgA {}
#[At::<string>] class TgB {}
var_dump((new ReflectionClass('TgA'))->getAttributes()[0]->getName());
var_dump((new ReflectionClass('TgB'))->getAttributes()[0]->getName());

// ---- Catch: committed bare '<' and turbofish, multi-entry, nested-fused, selectivity ----
class MyEx<T> extends Exception {}
try { throw new MyEx<int>(); } catch (MyEx<int> $e) { echo "catch bare\n"; }
try { throw new MyEx<int>(); } catch (MyEx::<int> $e) { echo "catch fish\n"; }
try { throw new MyEx<int>(); } catch (RuntimeException|MyEx<int> $e) { echo "catch multi\n"; }
try { throw new MyEx<Vec<int>>(); } catch (MyEx<Vec<int>> $e) { echo "catch fused\n"; }
try {
    try { throw new MyEx<int>(); } catch (MyEx<string> $e) { echo "WRONG\n"; }
} catch (MyEx<int> $e) { echo "catch selective\n"; }

// ---- Committed bare '<' after new/instanceof stays committed with DNF/multi/nested ----
class P<K, V> {}
var_dump(get_class(new P<int, string>()));
var_dump(get_class(new Vec<int|string>()));
var_dump((new Vec<Vec<int>>()) instanceof Vec<Vec<int>>);

// ---- BC: comparisons and ternaries near '<' are untouched ----
define('A', 1); define('B', 5);
$d = 3;
var_dump(A < B ? 9 : $d);
var_dump(A < B);
?>
--EXPECT--
bool(true)
string(1) "X"
string(8) "Vec<int>"
prop enforced
string(8) "Vec<int>"
bool(true)
string(8) "Vec<int>"
string(12) "D1<Vec<int>>"
string(8) "Vec<int>"
string(5) "trait"
string(7) "At<int>"
string(10) "At<string>"
catch bare
catch fish
catch multi
catch fused
catch selective
string(13) "P<int,string>"
string(15) "Vec<int|string>"
bool(true)
int(9)
bool(true)
