--TEST--
Generic methods: the tracing JIT must not share compiled code between type-argument copies of one generic method
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=16M
opcache.jit_hot_func=2
--FILE--
<?php
// Every set of type arguments gets its own copy of keep(), but all copies
// share the template's opcodes, opline handlers and hot counters. Three
// keep<Dog>() calls make the shared entry counter hot and record an entry
// trace whose argument and return checks are compiled from the Dog copy.
// If that machine code is installed into the shared entry handler, every
// later keep<Cell>() call runs it too and fails with
// "must be of type Dog, Cell given".
class Cell {}
class Dog {}
class Registry {
    public function keep<U>(U $x): U { return $x; }
}

$r = new Registry();
$dog = new Dog();
$cell = new Cell();
var_dump(get_class($r->keep<Dog>($dog)));
var_dump(get_class($r->keep<Dog>($dog)));
var_dump(get_class($r->keep<Dog>($dog)));
var_dump(get_class($r->keep<Cell>($cell)));
var_dump(get_class($r->keep<Cell>($cell)));
var_dump(get_class($r->keep<Dog>($dog)));
?>
--EXPECT--
string(3) "Dog"
string(3) "Dog"
string(3) "Dog"
string(4) "Cell"
string(4) "Cell"
string(3) "Dog"
