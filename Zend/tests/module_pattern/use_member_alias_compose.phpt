--TEST--
Member aliases compose with namespaces, comma lists, and dynamic instantiation
--FILE--
<?php
eval('namespace BP; module NL {
    export BP\NL\Seq;
    export BP\NL\Base;
}');
eval('namespace BP\NL; module BP\NL;
    class Base {}
    class Seq extends Base {}');

// A namespaced consumer: the import binding wins over namespace prefixing, and
// a single `use` may bind several members in one comma-separated list.
eval('
namespace App;
use module BP\NL;
use NL:>Seq as S, NL:>Base as B;

$x = new S();
echo get_class($x), "\n";
var_dump($x instanceof B);
echo S::class, "\n";

// The alias is a compile-time name only: a dynamic (string) class name is the
// canonical FQCN and is never gated, exactly as for a `:>` reference.
$name = S::class;
echo get_class(new $name()), "\n";
');
?>
--EXPECT--
BP\NL\Seq
bool(true)
BP\NL\Seq
BP\NL\Seq
