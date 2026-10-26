--TEST--
Structs: basic declaration, construction and members
--FILE--
<?php

struct Money {
    const DEFAULT_CURRENCY = 'USD';

    public int $amount = 0;
    public string $currency = self::DEFAULT_CURRENCY;
}

struct Point {
    public function __construct(
        public float $x,
        public float $y,
    ) {}

    public function length(): float {
        return sqrt($this->x ** 2 + $this->y ** 2);
    }
}

$m = new Money();
var_dump($m->amount, $m->currency, Money::DEFAULT_CURRENCY);

$p = new Point(3.0, 4.0);
var_dump($p->x, $p->y, $p->length());
var_dump($p instanceof Point);

$rc = new ReflectionClass(Point::class);
var_dump($rc->isFinal());

?>
--EXPECT--
int(0)
string(3) "USD"
string(3) "USD"
float(3)
float(4)
float(5)
bool(true)
bool(true)
