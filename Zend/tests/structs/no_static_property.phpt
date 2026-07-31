--TEST--
Structs: static properties are not permitted
--FILE--
<?php

struct P {
    public static int $count = 0;
}

?>
--EXPECTF--
Fatal error: Struct P cannot include static properties in %s on line %d
