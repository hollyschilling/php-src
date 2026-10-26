--TEST--
A member alias may not shadow a reserved class name
--FILE--
<?php
eval('namespace BP; module NL { export BP\NL\Base; }');
eval('namespace BP\NL; module BP\NL; class Base {}');
eval('use module BP\NL; use NL:>Base as self;');
?>
--EXPECTF--
Fatal error: Cannot use NL:>Base as self because 'self' is a special class name in %s : eval()'d code on line %d
