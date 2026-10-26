--TEST--
Structs: abstract methods are not permitted
--FILE--
<?php

struct P {
    abstract public function f(): void;
}

?>
--EXPECTF--
Fatal error: Struct method P::f() must not be abstract in %s on line %d
