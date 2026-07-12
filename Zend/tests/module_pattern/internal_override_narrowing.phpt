--TEST--
An override may not introduce internal on a non-internal parent member
--FILE--
<?php
class P {
    public function m() {}
}
class C extends P {
    internal function m() {}
}
?>
--EXPECTF--
Fatal error: Access level to C::m() must not be internal (as in class P) in %s on line %d
