--TEST--
Structs: the typed-shape rule applies to trait-composed properties
--FILE--
<?php

trait T {
    public $untyped;
}

struct P {
    use T;
}

?>
--EXPECTF--
Fatal error: Struct property P::$untyped must have type in %s on line %d
