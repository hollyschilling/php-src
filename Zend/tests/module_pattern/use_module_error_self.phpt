--TEST--
use module: self-import is a compile error
--FILE--
<?php
module My\Own;
use module My\Own;
?>
--EXPECTF--
Fatal error: Cannot import module My\Own from inside itself in %s on line %d
