--TEST--
A gated member alias of a generic template covers its instantiations at every acquisition site
--FILE--
<?php
eval('namespace BP; module NL {
    export BP\NL\Box;
    export BP\NL\Feed;
}');
eval('namespace BP\NL; module BP\NL;
    class Box<T> {
        const LABEL = "boxlabel";
        public function __construct(public T $v) {}
        public static function of(T $v): static { return new static($v); }
    }
    interface Feed<T> { public function next(): T; }');

eval('
use module BP\NL;
use NL:>Box as B;
use NL:>Feed as F;

$x = new B<int>(42);                       // acquisition
echo "new:      ", get_class($x), "\n";
echo "static:   ", B<int>::of(7)->v, "\n"; // static acquisition
echo "const:    ", B<int>::LABEL, "\n";
echo "::class:  ", B<int>::class, "\n";    // observation -> plain mangled FQCN
var_dump($x instanceof B<int>);            // observation
var_dump($x instanceof \BP\NL\Box<int>);   // plain spelling agrees: ONE identity

class SubBox extends B<int> {}             // extends via alias (acquisition)
echo "extends:  ", get_parent_class(new SubBox(1)), "\n";

class Ints implements F<int> {             // implements via alias (acquisition)
    public function next(): int { return 3; }
}
echo "impl:     ", (new Ints)->next(), "\n";

function takes(B<int> $b): int { return $b->v; }  // type position: plain, ungated
echo "typedecl: ", takes($x), "\n";

// The alias and the module-qualified spelling stamp the SAME instantiation.
var_dump(B<string>::class === NL:>Box<string>::class);
');

// Containment unchanged: the plain spelling is still gated outside the module.
try {
    (function () { new \BP\NL\Box<int>(1); })();
} catch (\Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
new:      BP\NL\Box<int>
static:   7
const:    boxlabel
::class:  BP\NL\Box<int>
bool(true)
bool(true)
extends:  BP\NL\Box<int>
impl:     3
typedecl: 42
bool(true)
Cannot access class BP\NL\Box<int> of module BP\NL from outside the module; import the module with 'use module'
