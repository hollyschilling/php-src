--TEST--
use extension imports a named extension into the current file
--FILE--
<?php
use extension VecUtils;

require __DIR__ . '/ext_decl.inc';

var_dump((new Vec([1, 2, 3]))->sum());
?>
--EXPECT--
int(6)
