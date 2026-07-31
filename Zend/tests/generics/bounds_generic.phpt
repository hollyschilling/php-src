--TEST--
Generic bounds: 'T: Comparable<T>' and friends substitute per instantiation
--FILE--
<?php declare(strict_types=1);
interface Comparable<in C> { public function compareTo(C $o): int; }
class Price implements Comparable<Price> {
    public function __construct(public int $cents = 0) {}
    public function compareTo(Price $o): int { return $this->cents <=> $o->cents; }
}
class Plain {}

// the classic F-bound
class Sorted<T: Comparable<T>> { public function __construct() {} }
echo (new Sorted<Price>())::class, "\n";
try { $c = 'Sorted<Plain>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $c = 'Sorted<int>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// sibling-parameter bounds
class Box<T> { public function __construct() {} }
class Pair<K, V: Box<K>> { public function __construct() {} }
echo (new Pair<Price, Box<Price>>())::class, "\n";
try { $c = 'Pair<Price,Box<Plain>>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// DNF bounds with parameter members; the error prints the substituted bound
class Flex<T: Comparable<T>|Stringable> { public function __construct() {} }
class S implements Stringable { public function __toString(): string { return "s"; } }
echo (new Flex<Price>())::class, "\n";
echo (new Flex<S>())::class, "\n";
try { $c = 'Flex<Plain>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// bound satisfaction composes with variance edges: Comparable is 'in', so an
// implementor of Comparable<Animal> satisfies the substituted Comparable<Cat>
class Animal {} class Cat extends Animal {}
class AnyCmp implements Comparable<Animal> {
    public function compareTo(Animal $o): int { return 0; }
}
class SortCats<T: Comparable<Cat>> { public function __construct() {} }
echo (new SortCats<AnyCmp>())::class, "\n";

// a bare sibling reference: V must be exactly (a subtype of) K
class Guard<K, V: K> { public function __construct() {} }
class Sub extends Price {}
echo (new Guard<Price, Sub>())::class, "\n";
try { $c = 'Guard<Price,Plain>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
Sorted<Price>
Plain does not satisfy the bound Comparable<Plain> of type parameter T on Sorted
int does not satisfy the bound Comparable<int> of type parameter T on Sorted
Pair<Price,Box<Price>>
Box<Plain> does not satisfy the bound Box<Price> of type parameter V on Pair
Flex<Price>
Flex<S>
Plain does not satisfy the bound Comparable<Plain>|Stringable of type parameter T on Flex
SortCats<AnyCmp>
Guard<Price,Sub>
Plain does not satisfy the bound Price of type parameter V on Guard
