--TEST--
Cross-feature: mutating extension methods on struct targets
--FILE--
<?php
struct Point {
    public function __construct(public float $x = 0.0, public float $y = 0.0) {}
    public function tryFromNonMutating(): void { $this->reset(); }
}
extension Point $p {
    public function reset() mutating: void { $p->x = 0.0; $p->y = 0.0; }
    public function shift(float $d) mutating: void { $p->x += $d; }
    public function len(): float { return sqrt($p->x * $p->x + $p->y * $p->y); }
    public function aliasWrite() mutating: void { $q = $p; $q->x = 99.0; }
}

$a = new Point(3.0, 4.0);
$b = $a;                    // value copy
var_dump($a->len());        // non-mutating extension read
$a->reset();
$a->shift(1.5);
var_dump($a->x);
var_dump($a->y);
var_dump($b->x);            // copy unaffected
var_dump($b->y);
$a->aliasWrite();           // alias write separates the alias, not the receiver
var_dump($a->x);

// $this chain rule extends to mutating extension methods
$q = new Point(1.0);
try { $q->tryFromNonMutating(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
float(5)
float(1.5)
float(0)
float(3)
float(4)
float(1.5)
Cannot call mutating method Point::reset() on $this in a non-mutating method
