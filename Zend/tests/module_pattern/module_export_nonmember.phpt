--TEST--
Exports must be members: exporting a foreign or module-less class fails at first resolution
--FILE--
<?php
eval('namespace Out; class Freeloader {}');
eval('namespace Out; module Club { export Out\Freeloader; }');
eval('use module Out\Club; class A { public function f() { return new Club:>Freeloader(); } }');
try {
    (new A)->f();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Class Out\Freeloader is exported by module Out\Club but is not a member of it
