--TEST--
Generics P1: templates persist in opcache SHM; stamping clones from immutable templates
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.protect_memory=1
--FILE--
<?php
class Vec<T: Countable> {
    private array $items = [];
    public function push(T $item): void { $this->items[] = $item; }
    public function pop(): ?T { return array_pop($this->items); }
}
class Bag implements Countable {
    public function count(): int { return 1; }
}
trait Cache<T> {
    private ?T $cached = null;
    public function remember(T $v): T { return $this->cached = $v; }
}
class Repo { use Cache<Bag>; }
interface Collection<T> { public function add(T $item): void; }
class BagCol implements Collection<Bag> {
    public function add(Bag $item): void {}
}
class Registry<T: Exception> {
    public function make(string $m): T { return new T($m); }
    public function name(): string { return T::class; }
}

var_dump(opcache_get_status(false)['opcache_enabled'] ?? false);

$v = new Vec<Bag>();
$v->push(new Bag);
var_dump(get_class($v->pop()));
try { $v->push(new DateTime); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
try { new Vec<stdClass>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

$r = new Repo();
var_dump(get_class($r->remember(new Bag)));
var_dump(new BagCol() instanceof Collection<Bag>);

$g = new Registry<RuntimeException>();
var_dump(get_class($g->make("x")));
var_dump($g->name());
var_dump(Vec<Bag>::class);
?>
--EXPECTF--
bool(true)
string(3) "Bag"
Vec<Bag>::push(): Argument #1 ($item) must be of type Bag, DateTime given, called in %s on line %d
stdClass does not satisfy the bound Countable of type parameter T on Vec
string(3) "Bag"
bool(true)
string(16) "RuntimeException"
string(16) "RuntimeException"
string(8) "Vec<Bag>"
