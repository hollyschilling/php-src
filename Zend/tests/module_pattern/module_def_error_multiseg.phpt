--TEST--
Module definition name must be a single identifier
--FILE--
<?php
module A\B\C { }
?>
--EXPECTF--
Fatal error: Module definition name must be a single identifier; the enclosing namespace supplies the prefix of the FQMN in %s on line %d
