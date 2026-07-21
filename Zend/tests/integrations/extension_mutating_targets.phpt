--TEST--
Cross-feature: mutating markers on non-struct extension targets are inert; interface extensions serve structs
--FILE--
<?php
interface Measurable { public function unit(): string; }
struct Dist implements Measurable {
    public function __construct(public float $m = 0.0) {}
    public function unit(): string { return "m"; }
}
class Weight implements Measurable {
    public float $kg = 0.0;
    public function unit(): string { return "kg"; }
}

// interface-targeted non-mutating extension serves both kinds of receiver
extension Measurable $r {
    public function label(): string { return get_class($r) . ":" . $r->unit(); }
}
var_dump((new Weight)->label());
var_dump((new Dist(2.0))->label());

// interface-targeted mutating extension: inert on class receivers
// (reference semantics make every method effectively mutating)
extension Measurable $m2 {
    public function clearKg() mutating: void { $m2->kg = 0.0; }
}
$w = new Weight();
$w->kg = 5.0;
$w->clearKg();
var_dump($w->kg);
?>
--EXPECT--
string(9) "Weight:kg"
string(6) "Dist:m"
float(0)
