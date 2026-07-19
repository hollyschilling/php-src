--TEST--
Generics M2: bounds are enforced at stamp time, including bound-kind validation
--FILE--
<?php
class Sorted<T implements Stringable> { public function add(T $x): void {} }
class Good implements Stringable { public function __toString(): string { return "g"; } }

$s = new Sorted<Good>();
echo get_class($s), "\n";
try { new Sorted<stdClass>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { new Sorted<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

class Node<T extends Exception> { public function wrap(T $e): T { return $e; } }
$n = new Node<RuntimeException>();
echo get_class($n->wrap(new RuntimeException("x"))), "\n";
try { new Node<Stringable>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// implements-bound naming a class is a kind violation
class Base {}
class Wrong<T implements Base> {}
try { new Wrong<Base>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
Sorted<Good>
stdClass does not satisfy the bound Stringable of type parameter T on Sorted
Cannot stamp Sorted<int>: scalar type argument does not satisfy the bound Stringable of type parameter T
RuntimeException
Stringable does not satisfy the bound Exception of type parameter T on Node
Bound Base of type parameter T on Wrong must be an interface (declared with "implements")
