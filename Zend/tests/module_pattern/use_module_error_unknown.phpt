--TEST--
use module: importing an undefined module without a loader is a compile error
--FILE--
<?php
use module Nope\Missing;
?>
--EXPECTF--
Fatal error: Module Nope\Missing is not defined; load its definition file before this file or register a loader with module_loader_register() in %s on line %d
