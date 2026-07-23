--TEST--
Module-qualified names (Mod:>Member) are usable as generic type arguments in every position
--FILE--
<?php
require __DIR__ . '/generic_arg_defs.inc';

// The consumer imports the module and names its members as type arguments.
// (eval so the definitions above are registered before this compiles.)
eval(<<<'PHP'
namespace App;
use module Acme\Kernel;

class Vec<T> { public array $i = []; public function push(T $v): void { $this->i[] = $v; } }
interface Coll<T> {}
class Base<T> {}
class Pair<K, V> {}

// Declaration contexts.
class C1 extends Vec<Kernel:>Widget> {}
class C2 implements Coll<Kernel:>Widget> {}
class C3<T> extends Base<Kernel:>Widget> {}

// Type positions.
function f(Vec<Kernel:>Widget> $x): Vec<Kernel:>Widget> { return $x; }
class Holder { public ?Vec<Kernel:>Widget> $v = null; }

// Expression positions: these need the lexer's bounded lookahead to treat
// ":>" atomically, so its '>' does not read as the end of the list.
$v = new Vec<Kernel:>Widget>();
var_dump(get_class($v));
var_dump($v instanceof Vec<Kernel:>Widget>);
var_dump(Vec<Kernel:>Widget>::class);

// The argument is the real module class, enforced as usual.
$v->push(new Kernel:>Widget());
var_dump(count($v->i));
try {
    $v->push(new Kernel:>Bag());
} catch (\TypeError $e) {
    echo $e->getMessage(), "\n";
}

// Multiple arguments, mixed with concrete types, and nested.
var_dump(get_class(new Pair<Kernel:>Widget, string>()));
var_dump(get_class(new Vec<Pair<Kernel:>Widget, Kernel:>Bag>>()));

// Declaration contexts resolved to the same instantiation.
var_dump(get_parent_class(new C1()));
var_dump((new C3<int>()) instanceof Base<Kernel:>Widget>);

// Type positions enforce the substituted type.
f($v);
$h = new Holder();
$h->v = $v;
try {
    $h->v = new Vec<Kernel:>Bag>();
} catch (\TypeError $e) {
    echo "property enforced\n";
}
PHP);
?>
--EXPECTF--
string(20) "App\Vec<Acme\Widget>"
bool(true)
string(20) "App\Vec<Acme\Widget>"
int(1)
App\Vec<Acme\Widget>::push(): Argument #1 ($v) must be of type Acme\Widget, Acme\Bag given, called in %s on line %d
string(28) "App\Pair<Acme\Widget,string>"
string(39) "App\Vec<App\Pair<Acme\Widget,Acme\Bag>>"
string(20) "App\Vec<Acme\Widget>"
bool(true)
property enforced
