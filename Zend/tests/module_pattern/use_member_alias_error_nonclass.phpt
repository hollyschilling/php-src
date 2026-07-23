--TEST--
A module-qualified name may only be imported as a class (not function/const)
--FILE--
<?php
eval('namespace BP; module NL { export BP\NL\Base; }');
eval('namespace BP\NL; module BP\NL; class Base {}');
eval('use module BP\NL; use function NL:>Base as B;');
?>
--EXPECTF--
Fatal error: A module-qualified name may only be imported as a class, not a function in %s : eval()'d code on line %d
