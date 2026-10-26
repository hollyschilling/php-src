--TEST--
Structs: a trait's mutating method is usable by a class, with the marker ignored
--FILE--
<?php
trait Bumps {
    public function bump() mutating: void { $this->n++; }
}
class C {
    public int $n = 0;
    use Bumps;
}
$c = new C();
$c->bump();
var_dump($c->n);
?>
--EXPECT--
int(1)
