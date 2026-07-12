--TEST--
Within the declaring module an internal member may be overridden, including widening to public
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';
?>
<?php
// Same module, separate compilation context
eval(<<<'PHP'
namespace Acme;

module Acme\Kernel;

class SpecialWidget extends Widget {
    internal function step(): string { return 'special'; }
}

class PublicWidget extends Widget {
    public function step(): string { return 'widened'; }
}
PHP);

$sw = new Acme\SpecialWidget();
var_dump($sw->runStep());

$pw = new Acme\PublicWidget();
var_dump($pw->step());  // widened to public: callable from outside
?>
--EXPECT--
string(7) "special"
string(7) "widened"
