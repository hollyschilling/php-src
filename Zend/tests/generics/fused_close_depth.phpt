--TEST--
'>>' and deeper fused closes are consumed by the grammar at any nesting depth
--FILE--
<?php
class Vec<T> { public function __construct() {} }
class Box<T> { public function __construct() {} }
class Pair<A, B> { public function __construct() {} }

// type positions (plain '<', no heuristic involvement)
function f(Vec<Vec<int>> $v): Vec<Box<Vec<int>>> { return new Vec<Box<Vec<int>>>(); }
class Holder {
    public ?Pair<Box<int>, Vec<string>> $p = null;
}

// depth 2..4 at expression sites
var_dump((new Vec<Vec<int>>())::class);
var_dump((new Vec<Box<Vec<int>>>())::class);
var_dump((new Vec<Box<Vec<Box<int>>>>())::class);

// fused close where the last argument is nested but earlier ones are not
var_dump((new Pair<int, Vec<string>>())::class);
// ...and where the nested argument is not last (normal '>' close)
var_dump((new Pair<Vec<string>, int>())::class);

// instanceof with fused close
var_dump(new Vec<Vec<int>>() instanceof Vec<Vec<int>>);

// static access with fused close ('::' follow)
var_dump(Vec<Vec<int>>::class);

// return value flows
var_dump(f(new Vec<Vec<int>>())::class);
?>
--EXPECT--
string(13) "Vec<Vec<int>>"
string(18) "Vec<Box<Vec<int>>>"
string(23) "Vec<Box<Vec<Box<int>>>>"
string(21) "Pair<int,Vec<string>>"
string(21) "Pair<Vec<string>,int>"
bool(true)
string(13) "Vec<Vec<int>>"
string(18) "Vec<Box<Vec<int>>>"
