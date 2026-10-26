--TEST--
Enums cannot be readonly
--FILE--
<?php

readonly enum Foo
{
}

?>
--EXPECTF--
Fatal error: Cannot use the readonly modifier on an enum in %s on line %d
