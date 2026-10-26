--TEST--
Structs: magic methods are not permitted (__get)
--FILE--
<?php

struct P {
    public int $x = 1;
    public function __get(string $name): mixed { return null; }
}

?>
--EXPECTF--
Fatal error: Struct P cannot include magic method __get() in %s on line %d
