--TEST--
Structs: the static-property ban applies to trait-composed members
--FILE--
<?php

trait T {
    public static int $count = 0;
}

struct P {
    use T;
}

?>
--EXPECTF--
Fatal error: Struct P cannot include static properties in %s on line %d
