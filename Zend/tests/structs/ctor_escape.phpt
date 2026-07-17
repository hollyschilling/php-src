--TEST--
Structs: a constructor that exports $this throws; a local copy does not
--FILE--
<?php

class Registry { public static $held; }

struct Leaky {
    public function __construct(public int $n) {
        Registry::$held = $this;
    }
}

try {
    $x = new Leaky(5);
    echo "no error\n";
} catch (\Error $e) {
    echo $e->getMessage(), "\n";
}

// Assigning $this to a local is not an escape: the local dies with the frame.
struct Fine {
    public int $doubled;
    public function __construct(public int $n) {
        $copy = $this;
        $copy->n = 999;       // mutates the local copy only
        $this->doubled = $n * 2;
    }
}

$f = new Fine(21);
var_dump($f->n, $f->doubled);

?>
--EXPECTF--
Cannot export $this from constructor of value class Leaky
int(21)
int(42)
