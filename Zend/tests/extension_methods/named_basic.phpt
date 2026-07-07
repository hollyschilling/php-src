--TEST--
Named extension blocks (extension Name on Target): declaring file is auto-activated
--FILE--
<?php
class Vec {
    public function __construct(public array $v) {}
}

extension VecUtils on Vec $vec {
    public function sum(): int { return array_sum($vec->v); }
}

var_dump((new Vec([1, 2, 3]))->sum());
?>
--EXPECT--
int(6)
