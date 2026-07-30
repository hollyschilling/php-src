--TEST--
Variance: method-level type parameters cannot carry in/out
--FILE--
<?php
interface I {
    public function m<out U>(): U;
}
?>
--EXPECTF--
Fatal error: Variance annotations are only permitted on interface type parameters (parameter U) in %s on line %d
