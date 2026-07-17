--TEST--
Structs: the magic-method ban applies to trait-composed members
--FILE--
<?php

trait T {
    public function __get(string $name): mixed { return null; }
}

struct P {
    use T;
    public int $x = 1;
}

?>
--EXPECTF--
Fatal error: Struct P cannot include magic method __get() in %s on line %d
