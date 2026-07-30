--TEST--
Variance: variant parameters may not appear inside foreign generic references (v1)
--FILE--
<?php
class Box<T> { public function __construct() {} }
interface Bad<out T> {
    public function wrap(): Box<T>;
}
?>
--EXPECTF--
Fatal error: Covariant type parameter T of Bad may not appear inside arguments of another generic reference in this version (return type of wrap) in %s on line %d
