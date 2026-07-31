--TEST--
Structs: __destruct is not permitted (a lifetime hook would observe CoW separation)
--FILE--
<?php

struct P {
    public int $x = 1;
    public function __destruct() {}
}

?>
--EXPECTF--
Fatal error: Struct P cannot include magic method __destruct() in %s on line %d
