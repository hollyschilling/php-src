--TEST--
use Prefix:>Member naming a non-exported member is a compile error
--FILE--
<?php
eval('namespace BP; module NL { }');
eval('namespace BP\NL; module BP\NL; class Secret {}');
eval('use module BP\NL; use NL:>Secret as S;');
?>
--EXPECTF--
Fatal error: Module BP\NL has no exported member Secret in %s : eval()'d code on line %d
