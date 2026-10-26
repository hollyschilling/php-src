--TEST--
Aliased module members in observation positions carry the plain FQCN (types, Reflection, catch)
--FILE--
<?php
eval('namespace BP; module NL {
    export BP\NL\Val;
    export BP\NL\Boom;
}');
eval('namespace BP\NL; module BP\NL;
    class Val {}
    class Boom extends \RuntimeException {}');

// A type declaration is an observation site: it stores the plain canonical
// FQCN, so Reflection reports it without any provenance marker.
eval('
use module BP\NL;
use NL:>Val as V;

function want(V $v): V { return $v; }

$r = new ReflectionFunction("want");
echo "param:  ", $r->getParameters()[0]->getType(), "\n";
echo "return: ", $r->getReturnType(), "\n";
');

// An internal exception that escapes the module can be caught by its aliased
// type from outside — catch is observation, not acquisition. The alias is
// file-scoped, so the catch must sit in the same compilation unit as the use.
eval('namespace BP\NL; module BP\NL;
    function raise() { throw new Boom("kaboom"); }');
eval('
use module BP\NL;
use NL:>Boom as Boom;

try {
    \BP\NL\raise();
} catch (Boom $e) {
    echo "caught: ", get_class($e), " / ", $e->getMessage(), "\n";
}
');
?>
--EXPECT--
param:  BP\NL\Val
return: BP\NL\Val
caught: BP\NL\Boom / kaboom
