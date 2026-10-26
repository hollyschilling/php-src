--TEST--
Structs: property receivers lend their slot to mutating calls (the scoped borrow)
--FILE--
<?php

struct IntList {
    public array $items = [];
    public function append(int $v) mutating: void { $this->items[] = $v; }
    public function count(): int { return count($this->items); }
}

// The flagship: a struct collection held by a class, mutated through the
// property from inside and outside the class.
class Repo {
    public IntList $list;
    public function __construct() { $this->list = new IntList(); }
    public function add(int $v): void { $this->list->append($v); }
}
$r = new Repo();
$r->add(1);
$r->add(2);
$r->list->append(3);
var_dump($r->list->count());

// Copy-on-write composes: a shared value separates inside the slot.
$snapshot = $r->list;
$r->add(4);
var_dump($r->list->count(), $snapshot->count());

// The mutation is a property write: unwritable slots refuse the borrow
// (reads are untouched).
class Guarded {
    public private(set) IntList $l;
    public readonly IntList $r;
    public function __construct() { $this->l = new IntList(); $this->r = new IntList(); }
}
$g = new Guarded();
var_dump($g->l->count());
try { $g->l->append(1); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $g->r->append(1); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Struct-in-struct: $this-rooted chains inside mutating frames are exclusive
// by construction; an exclusive variable works from outside; a shared one
// refuses (the copy you would corrupt is exactly the one you must make).
struct Poly {
    public IntList $pts;
    public function __construct() { $this->pts = new IntList(); }
    public function add(int $v) mutating: void { $this->pts->append($v); }
}
$p = new Poly();
$p->add(1);
$p->pts->append(2);
var_dump($p->pts->count());
$alias = $p;
try { $p->pts->append(3); } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump($p->pts->count(), $alias->pts->count());

// Chains: class middles anchor the slot; struct middles do not (v1).
class Svc { public IntList $l; public function __construct() { $this->l = new IntList(); } }
class App { public Svc $svc; public function __construct() { $this->svc = new Svc(); } }
$a = new App();
$a->svc->l->append(1);
var_dump($a->svc->l->count());

struct Inner { public IntList $l; public function __construct() { $this->l = new IntList(); } }
class Outer2 { public Inner $in; public function __construct() { $this->in = new Inner(); } }
$o = new Outer2();
try { $o->in->l->append(1); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Unborrowable shapes keep erroring: dynamic method names, temporaries.
$m = 'append';
try { $r->list->$m(9); } catch (Error $e) { echo $e->getMessage(), "\n"; }
function makeRepo(): Repo { return new Repo(); }
try { makeRepo()->list->append(9); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Non-mutating calls through the same receiver shape are unchanged,
// including first-class callables; mutating FCCs stay banned.
var_dump($r->list->count());
$cnt = $r->list->count(...);
var_dump($cnt());
try { $f = $r->list->append(...); } catch (Error $e) { echo $e->getMessage(), "\n"; }

?>
--EXPECT--
int(3)
int(4)
int(3)
int(0)
Cannot call mutating method IntList::append() on this receiver; assign it to a variable first
Cannot call mutating method IntList::append() on this receiver; assign it to a variable first
int(2)
Cannot call mutating method IntList::append() on this receiver; assign it to a variable first
int(2)
int(2)
int(1)
Cannot call mutating method IntList::append() on this receiver; assign it to a variable first
Cannot call mutating method IntList::append() on this receiver; assign it to a variable first
Cannot call mutating method IntList::append() on this receiver; assign it to a variable first
int(4)
int(4)
Cannot create a first-class callable of mutating method IntList::append()
