--TEST--
Variance: by-reference parameters are invariant positions
--FILE--
<?php
interface Bad<out T> {
    public function fill(T &$slot): void;
}
?>
--EXPECTF--
Fatal error: Covariant type parameter T of Bad may not appear in an invariant position (parameter type of fill) in %s on line %d
