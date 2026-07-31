--TEST--
Structs: #[AllowDynamicProperties] is not permitted
--FILE--
<?php

#[AllowDynamicProperties]
struct P {
    public int $x = 1;
}

?>
--EXPECTF--
Fatal error: Cannot apply #[\AllowDynamicProperties] to struct P in %s on line %d
