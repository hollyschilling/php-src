--TEST--
Structs: mutating methods write a variable receiver in place, separating shared values first
--FILE--
<?php

struct Counter {
    public function __construct(public int $n = 0) {}
    public mutating function inc(): void { $this->n++; }
    public mutating function add(int $k): void { $this->n += $k; }
    public mutating function incTwice(): void { $this->inc(); self::inc(); }
}

// Exclusive receiver: writes land in the caller's variable.
$c = new Counter();
$c->inc();
$c->inc();
var_dump($c->n);

// Shared receiver: the call separates the caller's slot first (copy-on-write).
$a = new Counter(10);
$b = $a;
$b->inc();
var_dump($a->n, $b->n);

// A reference names the storage slot: both names see the write.
$r = &$a;
$r->add(5);
var_dump($a->n, $r->n);

// Nested $this chains inside a mutating frame stay borrowed and write in place
// ($this->inc() and self::inc() alike).
$d = new Counter();
$d->incTwice();
var_dump($d->n);

// Dynamic method names resolve normally; the receiver rule applies after
// resolution.
$m = 'inc';
$d->$m();
var_dump($d->n);

// By-ref foreach lends each array element as a writable slot.
$counters = [new Counter(1), new Counter(2)];
foreach ($counters as &$el) {
    $el->inc();
}
unset($el);
var_dump($counters[0]->n, $counters[1]->n);

// Non-mutating methods on the same struct still bind $this by value.
struct Probe {
    public int $x = 0;
    public mutating function set(int $v): void { $this->x = $v; }
    public function tryLeak(): int { $this->x = 99; return $this->x; }
}
$p = new Probe();
$p->set(7);
var_dump($p->tryLeak(), $p->x);

?>
--EXPECT--
int(2)
int(10)
int(11)
int(15)
int(15)
int(2)
int(3)
int(2)
int(3)
int(99)
int(7)
