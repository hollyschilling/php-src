--TEST--
Composite (DNF) arguments in type positions, instanceof, turbofish, nested members
--FILE--
<?php declare(strict_types=1);
interface B {} interface C {}
class D implements B, C {}
class A {}
class Vec<T> { public function __construct() {} }
class Box<T> { public function __construct() {} }

// parameter / return / property positions
function f(Vec<int|string> $v): Vec<A|(B&C)> { return new Vec<A|(B&C)>(); }
class H { public ?Vec<B&C> $h = null; }

var_dump(f(new Vec<int|string>())::class);
$h = new H;
$h->h = new Vec<B&C>();
var_dump($h->h::class);

// invariance: a different instantiation is a TypeError with precise names
try { f(new Vec<int>()); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// instanceof, including the canonically-converged spelling
var_dump(new Vec<int|string>() instanceof Vec<int|string>);
var_dump(new Vec<int|string>() instanceof Vec<string|int>);
var_dump(new Vec<int>() instanceof Vec<int|string>);

// turbofish with DNF at expression sites
var_dump(Vec::<A|null>::class);
var_dump((new Vec::<B&C>())::class);

// nested instantiations as members, fused '>>' closes
var_dump((new Vec<Box<int>|null>())::class);
var_dump((new Vec<A|Box<int>>())::class);
var_dump((new Vec<Box<int|string>>())::class);
?>
--EXPECTF--
string(12) "Vec<(B&C)|A>"
string(8) "Vec<B&C>"
f(): Argument #1 ($v) must be of type Vec<int|string>, Vec<int> given, called in %s on line %d
bool(true)
bool(true)
bool(false)
string(11) "Vec<A|null>"
string(8) "Vec<B&C>"
string(18) "Vec<Box<int>|null>"
string(15) "Vec<A|Box<int>>"
string(20) "Vec<Box<int|string>>"
