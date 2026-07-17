--TEST--
Structs: __toString is not permitted (Stringable is unsatisfiable)
--FILE--
<?php

struct P {
    public int $x = 1;
    public function __toString(): string { return 'p'; }
}

?>
--EXPECTF--
Fatal error: Struct P cannot include magic method __toString() in %s on line %d
