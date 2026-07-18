--TEST--
Structs: a set hook that exports $this throws
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
try {
    $s->y = 5;
    echo "no error\n";
} catch (\Error $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECTF--
Cannot export $this from a set hook of struct S
