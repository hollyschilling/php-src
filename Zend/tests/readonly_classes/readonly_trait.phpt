--TEST--
Traits cannot be readonly
--FILE--
<?php

readonly trait Foo
{
}

?>
--EXPECTF--
Fatal error: Cannot use the readonly modifier on a trait in %s on line %d
