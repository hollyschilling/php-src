--TEST--
Extension names share the class symbol namespace: declaring an extension under a taken name is fatal
--FILE--
<?php
class Conflict {}

require __DIR__ . '/autoload_collision_decl.inc';
?>
--EXPECTF--
Fatal error: Cannot declare extension Conflict, because the name is already in use in %sautoload_collision_decl.inc on line %d
