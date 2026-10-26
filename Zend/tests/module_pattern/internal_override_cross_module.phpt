--TEST--
Overriding an internal member from outside the declaring module is an inheritance-time error
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';
?>
<?php
eval('use module Acme\Kernel;
class Hijack extends Kernel:>Widget {
    public function step(): string { return "hijacked"; }
}');
?>
--EXPECTF--
Fatal error: Cannot override internal method Acme\Widget::step() from outside its module in %s : eval()'d code on line %d
