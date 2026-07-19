--TEST--
Structs: interfaces declare mutating requirements; the color may be removed along an edge, never added
--FILE--
<?php

// A colored requirement is permission, not obligation: satisfied by a
// mutating struct method, an uncolored struct method, or any class method.
interface Collection {
    public function add(int $v) mutating: void;
}

struct SMut implements Collection {
    public array $items = [];
    public function add(int $v) mutating: void { $this->items[] = $v; }
}
struct SUncolored implements Collection {
    public array $items = [];
    public function add(int $v): void {}          // stronger guarantee: allowed
}
class CPlain implements Collection {
    public array $items = [];
    public function add(int $v): void { $this->items[] = $v; }
}

$s = new SMut();
$s->add(1);
$s->add(2);
$c = new CPlain();
$c->add(7);
var_dump(count($s->items), count($c->items));

// Polymorphic dispatch: the receiver rules follow the resolved callee.
// An interface-typed parameter is a variable -- always lendable.
function addTwice(Collection $col): Collection {
    $col->add(10);
    $col->add(20);
    return $col;
}
$s2 = addTwice(new SMut());
$c2 = addTwice(new CPlain());
var_dump(count($s2->items), count($c2->items));

// Value vs reference stays visible through the contract, as designed:
// the struct caller's copy is unaffected, the class caller's object is shared.
$sOrig = new SMut();
addTwice($sOrig);
var_dump(count($sOrig->items));
$cOrig = new CPlain();
addTwice($cOrig);
var_dump(count($cOrig->items));

// An interface extending an interface may remove the color (tightening).
interface Narrowed extends Collection {
    public function add(int $v): void;
}
class CNarrow implements Narrowed {
    public function add(int $v): void {}
}
var_dump(new CNarrow() instanceof Collection);

// A trait's abstract colored requirement is valid in a class: permission
// the class implementation does not use.
trait Bumpable {
    abstract public function bump() mutating: void;
}
class CBump {
    use Bumpable;
    public int $n = 0;
    public function bump(): void { $this->n++; }
}
$b = new CBump();
$b->bump();
var_dump($b->n);

?>
--EXPECT--
int(2)
int(1)
int(2)
int(2)
int(0)
int(2)
bool(true)
int(1)
