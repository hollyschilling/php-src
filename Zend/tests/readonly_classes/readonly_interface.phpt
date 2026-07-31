--TEST--
Interfaces cannot be readonly
--FILE--
<?php

readonly interface Foo
{
}

?>
--EXPECTF--
Fatal error: Cannot use the readonly modifier on an interface in %s on line %d
