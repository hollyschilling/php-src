--TEST--
Structs: a trait's abstract requirement must be satisfied, and a struct cannot be made abstract
--FILE--
<?php

trait T {
    abstract public function f(): void;
}

struct P {
    use T;
}

?>
--EXPECTF--
Fatal error: Struct P must implement 1 abstract method (P::f) in %s on line %d
