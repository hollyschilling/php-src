--TEST--
Param-dependent composite members: T|Foo, V|false, Vec<T>|null, Box<T|null>
--FILE--
<?php declare(strict_types=1);
class Foo {} interface B {} interface C {} class D implements B, C {}
class Vec<T> { public function __construct() {} }

// the headline signatures
class Map<K, V> {
    private array $i = [];
    public Vec<K>|null $keyCache = null;
    public function set(K $k, V $v): void { $this->i[serialize($k)] = $v; }
    public function get(K $k): V|false { return $this->i[serialize($k)] ?? false; }
    public function keys(): Vec<K>|null { return $this->keyCache ??= new Vec<K>(); }
}
$m = new Map<int, Foo>();
$m->set(1, new Foo);
var_dump($m->get(1) instanceof Foo);
var_dump($m->get(99));
var_dump($m->keys()::class);
var_dump($m->keyCache instanceof Vec<int>);

// scalar arguments fold into the union: V = string makes V|false string|false
$s = new Map<string, string>();
$s->set("a", "x");
var_dump($s->get("a"));
try { $r = new Map<string, int>(); $r->set("a", "no"); }
catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// bare T members: class, scalar-fold, dedupe
class Opt<T> { public function pick(T|Foo $x): string { return get_debug_type($x); } }
var_dump((new Opt<B>())->pick(new D));
var_dump((new Opt<int>())->pick(7));
var_dump((new Opt<int>())->pick(new Foo));
var_dump((new Opt<Foo>())->pick(new Foo));   // T|Foo deduped to Foo

// a union ARGUMENT splices into a union list
var_dump((new Opt<B|C>())->pick(new D));

// composite ARGUMENT with a parameter member, substituted + canonicalized:
// Vec<T|null> with T=Foo is the SAME class as a direct Vec<Foo|null>
class Pipe<T> { public function make(): object { return new Vec<T|null>(); } }
$sub = (new Pipe<Foo>())->make();
var_dump($sub::class, $sub::class === (new Vec<Foo|null>())::class);
class Dup<T> { public function make(): object { return new Vec<T|Foo>(); } }
var_dump((new Dup<Foo>())->make()::class);

// intersections: class-shaped arguments only, validated BEFORE any clone
class Meet<T> { public function need(T&B $x): string { return get_debug_type($x); } }
var_dump((new Meet<C>())->need(new D));
try { $c = 'Meet<int>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $c = 'Meet<Foo|B>'; new $c(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
// intersection argument INTO an intersection splices
class MeetI { }
var_dump((new Meet<B&C>())->need(new D));
?>
--EXPECTF--
bool(true)
bool(false)
string(8) "Vec<int>"
bool(true)
string(1) "x"
Map<string,int>::set(): Argument #2 ($v) must be of type int, string given, called in %s on line %d
string(1) "D"
string(3) "int"
string(3) "Foo"
string(3) "Foo"
string(1) "D"
string(13) "Vec<Foo|null>"
bool(true)
string(8) "Vec<Foo>"
string(1) "D"
Cannot stamp Meet<int>: type argument int for parameter T cannot be used inside an intersection type
Cannot stamp Meet<Foo|B>: type argument Foo|B for parameter T cannot be used inside an intersection type
string(1) "D"
