--TEST--
Generics M2: ':' bounds are enforced at stamp time; the relation is inferred from the bound
--FILE--
<?php
class Sorted<T: Stringable> { public function add(T $x): void {} }
class Good implements Stringable { public function __toString(): string { return "g"; } }

$s = new Sorted<Good>();
echo get_class($s), "\n";
try { new Sorted<stdClass>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { new Sorted<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

class Node<T: Exception> { public function wrap(T $e): T { return $e; } }
$n = new Node<RuntimeException>();
echo get_class($n->wrap(new RuntimeException("x"))), "\n";
try { new Node<Stringable>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// class bounds carry no implements/extends distinction: the relation is
// inferred from what the bound resolves to
class Base {}
class Sub extends Base {}
class Wide<T: Base> {}
echo get_class(new Wide<Sub>()), "\n";
try { new Wide<stdClass>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
Sorted<Good>
stdClass does not satisfy the bound Stringable of type parameter T on Sorted
int does not satisfy the bound Stringable of type parameter T on Sorted
RuntimeException
Stringable does not satisfy the bound Exception of type parameter T on Node
Wide<Sub>
stdClass does not satisfy the bound Base of type parameter T on Wide
