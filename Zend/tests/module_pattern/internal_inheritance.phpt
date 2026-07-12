--TEST--
internal members are inherited; accessibility stays bound to the declaring class's module
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';

// Null-module subclass: inheriting without overriding is fine
class Sub extends Acme\Widget {}

$s = new Sub();
// Module code can still reach the inherited internal member through the subclass
var_dump($s->runStep());
// Outside code cannot
try {
    $s->step();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
string(7) "stepped"
Call to internal method Acme\Widget::step() from global scope
