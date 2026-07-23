--TEST--
A member alias collides with an existing class import under the same name
--FILE--
<?php
eval('namespace BP; module NL { export BP\NL\Base; }');
eval('namespace BP\NL; module BP\NL; class Base {}');
eval('use module BP\NL; use BP\NL\Base as B; use NL:>Base as B;');
?>
--EXPECTF--
Fatal error: Cannot use NL:>Base as B because the name is already in use in %s : eval()'d code on line %d
