--TEST--
Structs: a set hook may export $this; the escapee becomes an ordinary shared value
--FILE--
<?php

class Registry { public static $held; }

struct S {
    public int $x = 0;
    public int $y {
        get => $this->x;
        set (int $v) { $this->x = $v; Registry::$held = $this; }
    }
}

$s = new S();
$s->y = 5;

// After the call the escapee aliases the receiver's value -- exactly as if
// it had been assigned after the write.
var_dump($s->x, Registry::$held->x);

// The alias is severed by the next write to either side (copy-on-write).
$s->x = 9;
var_dump($s->x, Registry::$held->x);

Registry::$held->x = 40;
var_dump($s->x, Registry::$held->x);

?>
--EXPECT--
int(5)
int(5)
int(9)
int(5)
int(9)
int(40)
