--TEST--
Variance: covariant parameter may not appear in an input position
--FILE--
<?php
interface Bad<out T> {
    public function add(T $x): void;
}
?>
--EXPECTF--
Fatal error: Covariant type parameter T of Bad may not appear in an input position (parameter type of add) in %s on line %d
