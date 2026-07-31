--TEST--
Module-qualified names cannot be used as function names
--FILE--
<?php
eval('namespace N; module Real { export N\Thing; }');
eval('use module N\Real; class T3 { public function f() { return Real:>Thing(); } }');
?>
--EXPECTF--
Fatal error: Module-qualified names can only refer to classes in %s : eval()'d code on line %d
