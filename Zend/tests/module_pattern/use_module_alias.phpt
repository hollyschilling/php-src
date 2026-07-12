--TEST--
use module aliasing resolves colliding local names
--FILE--
<?php
eval('namespace N; module Real { export N\Thing; }');
eval('namespace N; module N\Real; class Thing {}');
eval('namespace M; module Real { export M\Item; }');
eval('namespace M; module M\Real; class Item {}');

eval('use module N\Real; use module M\Real as MReal;
class T4 {
    public function f(): array { return [new Real:>Thing(), new MReal:>Item()]; }
}');
[$a, $b] = (new T4)->f();
var_dump(get_class($a), get_class($b));
?>
--EXPECT--
string(7) "N\Thing"
string(6) "M\Item"
