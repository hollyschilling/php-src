--TEST--
Generic methods: opcache sizes the type parameters of a cached class's method copies (trait clone persisted into the inheritance cache)
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.protect_memory=1
--FILE--
<?php
// The trait's generic method is persisted once with the script. Linking
// Registry at run time clones that method (an arena copy sharing the
// persisted generic_params), and the linked class is persisted again into
// the inheritance cache. Both opcache passes must account for the method's
// type parameters at the same point, or the second persist dies with
// "Internal error: wrong class size calculation".
trait Maker {
    public function make<U>(): U { return new U(); }
}
class Cell {}
class Registry { use Maker; }

$r = new Registry();
var_dump(get_class($r->make<Cell>()));
var_dump(get_class((new Registry())->make<Cell>()));
?>
--EXPECT--
string(4) "Cell"
string(4) "Cell"
