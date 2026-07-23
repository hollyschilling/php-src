--TEST--
use Prefix:>Member requires the module to be imported first (source order)
--FILE--
<?php
eval('namespace BP; module NL { export BP\NL\Base; }');
eval('namespace BP\NL; module BP\NL; class Base {}');
// No `use module BP\NL;` precedes the member alias.
eval('use NL:>Base as B;');
?>
--EXPECTF--
Fatal error: No module imported with name "NL" (missing 'use module'?) in %s : eval()'d code on line %d
