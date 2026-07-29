--TEST--
':' bounds over composite (DNF) types: scalar unions, interface unions, intersections, array
--FILE--
<?php declare(strict_types=1);
interface Marked {}
interface Extra {}
class M1 implements Marked {}
class ME implements Marked, Extra {}
class Plain {}

// the two headline cases: keys and printables
class Map<K: int|string, V> { public function __construct() {} }
echo (new Map<int, Plain>())::class, "\n";
echo (new Map<string, Plain>())::class, "\n";
try { $c = 'Map<float,Plain>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $c = 'Map<Plain,Plain>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

class Printer<T: string|Stringable> { public function __construct() {} }
class S implements Stringable { public function __toString(): string { return "s"; } }
echo (new Printer<string>())::class, "\n";
echo (new Printer<S>())::class, "\n";
try { $c = 'Printer<int>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// composite ARG against composite BOUND: every arg member must satisfy
echo (new Printer<string|S>())::class, "\n";
try { $c = 'Printer<string|Plain>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// intersection bound: argument must satisfy all parts
class Both<T: Marked&Extra> { public function __construct() {} }
echo (new Both<ME>())::class, "\n";
try { $c = 'Both<M1>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// intersection ARGUMENT satisfies a class bound through any part
class Keep<T: Marked> { public function __construct() {} }
echo (new Keep<Extra&M1>())::class, "\n";

// array as a bound, and DNF with array
class Rows<T: array|Plain> { public function __construct() {} }
echo (new Rows<array>())::class, "\n";
echo (new Rows<Plain>())::class, "\n";
try { $c = 'Rows<int>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// nullable bound sugar
class Opt<T: ?Plain> { public function __construct() {} }
echo (new Opt<null|Plain>())::class, "\n";

// bound with a nested instantiation, closing with fused '>>'
class Vec<T> { public function __construct() {} }
class Holder<T: Vec<int>> { public function __construct() {} }
echo (new Holder<Vec<int>>())::class, "\n";
try { $c = 'Holder<Vec<string>>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// packs take ':' bounds too, checked elementwise
class Zip<...Ts: int|string> { public function __construct() {} }
echo (new Zip<int, string, int>())::class, "\n";
try { $c = 'Zip<int,float>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
Map<int,Plain>
Map<string,Plain>
float does not satisfy the bound int|string of type parameter K on Map
Plain does not satisfy the bound int|string of type parameter K on Map
Printer<string>
Printer<S>
int does not satisfy the bound string|Stringable of type parameter T on Printer
Printer<S|string>
Plain|string does not satisfy the bound string|Stringable of type parameter T on Printer
Both<ME>
M1 does not satisfy the bound Extra&Marked of type parameter T on Both
Keep<Extra&M1>
Rows<array>
Rows<Plain>
int does not satisfy the bound array|Plain of type parameter T on Rows
Opt<null|Plain>
Holder<Vec<int>>
Vec<string> does not satisfy the bound Vec<int> of type parameter T on Holder
Zip<int,string,int>
float does not satisfy the bound int|string of type parameter Ts on Zip
