--TEST--
Structs: set hooks persist and copy; get hooks never mutate the value
--FILE--
<?php

struct Temp {
    public function __construct(private float $celsius) {}

    public float $fahrenheit {
        get => $this->celsius * 9 / 5 + 32;
        set (float $f) { $this->celsius = ($f - 32) * 5 / 9; }
    }

    public function celsius(): float { return $this->celsius; }
}

$t = new Temp(0.0);
var_dump($t->fahrenheit);

// A set hook persists its writes -- but only on the copy being written.
$u = $t;
$u->fahrenheit = 212.0;
var_dump($t->celsius(), $u->celsius(), $u->fahrenheit);

// A get hook that writes $this must not mutate the value: the write is discarded.
struct Counter {
    public function __construct(public int $n) {}
    public int $next {
        get { $this->n++; return $this->n; }
    }
}
$c = new Counter(5);
$first = $c->next;
$second = $c->next;
var_dump($first, $second, $c->n);

?>
--EXPECT--
float(32)
float(0)
float(100)
float(212)
int(6)
int(6)
int(5)
