--TEST--
Overriding an internal member from outside the declaring module is an inheritance-time error
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';

class Hijack extends Acme\Widget {
    public function step(): string { return 'hijacked'; }
}
?>
--EXPECTF--
Fatal error: Cannot override internal method Acme\Widget::step() from outside its module in %s on line %d
