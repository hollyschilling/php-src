--TEST--
Structs: mutating methods may export $this; only `new`-borne construction enforces non-escape
--FILE--
<?php

class Registry { public static array $held = []; }

struct Counter {
    public function __construct(public int $n = 0) {}
    public function inc() mutating: void { $this->n++; }
    public function leak() mutating: void {
        Registry::$held[] = $this;
        $this->n = 77;      // still lands in the receiver: the escapee shares it
    }
}

// Escape from a mutating method: at return the escapee aliases the receiver,
// as if it had been assigned right after the call.
$c = new Counter(1);
$c->leak();
var_dump($c->n, Registry::$held[0]->n);

// Copy-on-write severs the alias at the next write to either side.
$c->inc();
var_dump($c->n, Registry::$held[0]->n);

// Explicit re-initialization runs as an owned mutating call: escape allowed.
struct Reinit {
    public function __construct(public int $v = 0) {
        if ($this->v === 42) {
            Registry::$held[] = $this;
        }
    }
}
$r = new Reinit(1);
$r->__construct(42);
var_dump($r->v, Registry::$held[1]->v);
$r->v = 5;
var_dump($r->v, Registry::$held[1]->v);

// `new`-borne construction is the one transactional context: the instance is
// discarded on failure, so the classic register-during-construction footgun
// still breaks loudly instead of leaving a silently detaching alias.
struct Leaky {
    public function __construct(public int $n) { Registry::$held[] = $this; }
}
try {
    $x = new Leaky(5);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECT--
int(77)
int(77)
int(78)
int(77)
int(42)
int(42)
int(5)
int(42)
Cannot export $this from the constructor of struct Leaky
