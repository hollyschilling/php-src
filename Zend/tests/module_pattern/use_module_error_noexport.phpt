--TEST--
:> naming a non-exported member is a compile error
--FILE--
<?php
eval('namespace N; module Real { export N\Thing; }');
eval('use module N\Real; class T2 { public function f() { return new Real:>Ghost(); } }');
?>
--EXPECTF--
Fatal error: Module N\Real has no exported member Ghost in %s : eval()'d code on line %d
