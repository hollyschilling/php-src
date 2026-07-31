--TEST--
A file may declare module membership at most once
--FILE--
<?php
module Acme\Kernel;
module Other\Thing;
?>
--EXPECTF--
Fatal error: Module membership was already declared as Acme\Kernel in %s on line %d
