--TEST--
Generics: bare use of a generic trait errors; trait bounds enforce at stamp time
--FILE--
<?php
trait Cache<T> { public function remember(T $v): void {} }
trait Sortable<T: Stringable> { public function sortBy(T $k): void {} }

try {
    eval("class Broken { use Cache; }");
} catch (Error $e) { echo $e->getMessage(), "\n"; }

try {
    eval("class BadSort { use Sortable<stdClass>; }");
} catch (Error $e) { echo $e->getMessage(), "\n"; }

try {
    eval("class BadArity { use Cache<int,string>; }");
} catch (Error $e) { echo $e->getMessage(), "\n"; }

class Ok implements Stringable {
    public function __toString(): string { return "ok"; }
}
class GoodSort { use Sortable<Ok>; }
$g = new GoodSort();
$g->sortBy(new Ok());
echo "bounded trait ok\n";
?>
--EXPECT--
Broken cannot use generic trait Cache without type arguments
stdClass does not satisfy the bound Stringable of type parameter T on Sortable
Generic class Cache expects 1 type argument, 2 given
bounded trait ok
