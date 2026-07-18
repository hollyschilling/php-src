--TEST--
Structs: __construct is permitted and runs through object creation
--FILE--
<?php

struct P {
    public int $doubled;

    public function __construct(public int $x) {
        $this->doubled = $x * 2;
    }
}

$p = new P(21);
var_dump($p->x, $p->doubled);

?>
--EXPECT--
int(21)
int(42)
