--TEST--
use Prefix:>Member as Alias binds an exported member to a bare local name
--FILE--
<?php
eval('namespace BP; module NL {
    export BP\NL\Base;
    export BP\NL\Seq;
}');
eval('namespace BP\NL; module BP\NL;
    class Base { const TAG = "base"; }
    class Seq extends Base {
        public function hi() { return "hi"; }
        public static function of() { return "of"; }
    }');

// Aliased member: every acquisition site passes the module gate, and every
// observation site keeps the plain canonical FQCN.
eval('
use module BP\NL;
use NL:>Seq as S;
use NL:>Base as B;

$x = new S();                      // acquisition
echo "new:      ", get_class($x), "\n";
echo "method:   ", $x->hi(), "\n";
echo "static:   ", S::of(), "\n";  // static acquisition
echo "const:    ", S::TAG, "\n";   // inherited const, static acquisition
echo "::class:  ", S::class, "\n"; // observation -> plain FQCN
var_dump($x instanceof S);         // observation
var_dump($x instanceof B);

class Sub extends S {}             // extends via alias (acquisition)
echo "extends:  ", get_class(new Sub()), "\n";
var_dump((new Sub()) instanceof B);
');

// No explicit alias: the default alias is the member segment after ":>".
eval('
use module BP\NL;
use NL:>Seq;
echo "default:  ", get_class(new Seq()), "\n";
');
?>
--EXPECT--
new:      BP\NL\Seq
method:   hi
static:   of
const:    base
::class:  BP\NL\Seq
bool(true)
bool(true)
extends:  Sub
bool(true)
default:  BP\NL\Seq
