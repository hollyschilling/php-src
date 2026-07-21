--TEST--
A generic instantiation named through a module import (Mod:>Box<int>) passes the acquisition gate; bare references stay gated
--FILE--
<?php
require __DIR__ . '/generic_member.inc';

// The consumer imports the module and reaches the generic member through :>
// in every class-reference position: new, static call, class constant,
// ::class, and as a parameter type. (eval so the definition above is
// registered before the consumer compiles.)
eval(<<<'PHP'
namespace App;
use module Acme\Kernel;

$b = new Kernel:>Box<int>(42);
var_dump($b->get());

$c = Kernel:>Box<int>::of(99);
var_dump($c->get());

var_dump(Kernel:>Box<int>::LABEL);
var_dump(Kernel:>Box<int>::class);

function takesBox(Kernel:>Box<int> $b): int { return $b->get(); }
var_dump(takesBox($b));

// instanceof is an observation site: the plain FQCN spelling agrees.
var_dump($b instanceof \Acme\Box<int>);
PHP);

// Containment: a bare reference to the instantiation from outside the module,
// without `use module`, is still gated exactly like a non-generic member.
try {
    (function () { new \Acme\Box<int>(1); })();
} catch (\Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
int(42)
int(99)
string(8) "boxlabel"
string(13) "Acme\Box<int>"
int(42)
bool(true)
Cannot access class Acme\Box<int> of module Acme\Kernel from outside the module; import the module with 'use module'
