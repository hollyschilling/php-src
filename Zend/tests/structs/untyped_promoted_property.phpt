--TEST--
Structs: promoted constructor properties must have a declared type
--FILE--
<?php

struct P {
    public function __construct(public $x) {}
}

?>
--EXPECTF--
Fatal error: Struct property P::$x must have type in %s on line %d
