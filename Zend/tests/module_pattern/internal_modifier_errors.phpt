--TEST--
internal cannot combine with another access type modifier
--FILE--
<?php
class X {
    public internal function f() {}
}
?>
--EXPECTF--
Fatal error: Cannot combine the internal modifier with another access type modifier in %s on line %d
