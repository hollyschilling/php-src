--TEST--
Trait internal members are governed by the using class's module after flattening
--FILE--
<?php
// The trait itself lives in the null module
eval(<<<'PHP'
trait Stepper {
    internal function traitStep(): string { return 'trait-stepped'; }
}
PHP);

// A module class uses it: the member behaves as internal to that module
eval(<<<'PHP'
namespace Acme;

module Acme\Kernel;

class Machine {
    use \Stepper;
    public function go(): string { return $this->traitStep(); }
}

class MachineFriend {
    public function poke(Machine $m): string { return $m->traitStep(); }
}
PHP);

$m = new Acme\Machine();
var_dump($m->go());
var_dump((new Acme\MachineFriend())->poke($m));  // same module: OK
try {
    $m->traitStep();                              // null module: denied
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
string(13) "trait-stepped"
string(13) "trait-stepped"
Call to internal method Acme\Machine::traitStep() from global scope
