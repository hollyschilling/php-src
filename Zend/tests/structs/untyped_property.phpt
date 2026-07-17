--TEST--
Structs: every property must have a declared type
--FILE--
<?php

struct P {
    public $x;
}

?>
--EXPECTF--
Fatal error: Struct property P::$x must have type in %s on line %d
