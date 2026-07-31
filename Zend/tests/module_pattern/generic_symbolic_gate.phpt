--TEST--
Symbolic generic references cannot launder module acquisitions past the gate
--FILE--
<?php
eval('namespace BP; module NL { export BP\NL\Box; }');
eval('namespace BP\NL; module BP\NL;
    class Box<T> { public function __construct(public T $v) {} }');

// A consumer template routing a plain FQCN through its own type parameter
// used to bypass the acquisition gate entirely. Now it is gated at the
// substituted fetch, exactly like the concrete spelling.
eval('class Sneaky<T> {
    public function wrap(T $v): object { return new \BP\NL\Box<T>($v); }
}');
try {
    eval('(new \Sneaky<int>())->wrap(5);');
} catch (\Error $e) {
    echo $e->getMessage(), "\n";
}

// Importing the module does not unlock the plain spelling either; the
// acquisition must go through Mod:> or a gated alias, as for concrete refs.
eval('use module BP\NL;
    class Half<T> { public function wrap(T $v): object { return new \BP\NL\Box<T>($v); } }');
try {
    eval('use module BP\NL; (new \Half<int>())->wrap(5);');
} catch (\Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Cannot access class BP\NL\Box<int> of module BP\NL from outside the module; import the module with 'use module'
Cannot access class BP\NL\Box<int> of module BP\NL from outside the module; import the module with 'use module'
