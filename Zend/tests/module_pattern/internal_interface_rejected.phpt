--TEST--
Interfaces are public-only: internal interface members are rejected
--FILE--
<?php
interface I {
    internal function f();
}
?>
--EXPECTF--
Fatal error: Access type for interface method I::f() must be public in %s on line %d
