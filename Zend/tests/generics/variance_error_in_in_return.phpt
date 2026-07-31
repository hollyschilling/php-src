--TEST--
Variance: contravariant parameter may not appear in an output position
--FILE--
<?php
interface Bad<in T> {
    public function get(): T;
}
?>
--EXPECTF--
Fatal error: Contravariant type parameter T of Bad may not appear in an output position (return type of get) in %s on line %d
