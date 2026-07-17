--TEST--
Structs: __construct is the one permitted magic method
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
