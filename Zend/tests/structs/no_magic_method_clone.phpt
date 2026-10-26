--TEST--
Structs: __clone is not permitted (a copy hook would observe CoW separation)
--FILE--
<?php

struct P {
    public int $x = 1;
    public function __clone(): void {}
}

?>
--EXPECTF--
Fatal error: Struct P cannot include magic method __clone() in %s on line %d
