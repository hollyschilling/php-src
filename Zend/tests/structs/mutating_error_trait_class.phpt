--TEST--
Structs: a trait's mutating method cannot be used by a class
--FILE--
<?php
trait Bumps {
    public function bump() mutating: void { $this->n++; }
}
class C {
    public int $n = 0;
    use Bumps;
}
?>
--EXPECTF--
Fatal error: Class C cannot use mutating method Bumps::bump(); mutating methods require a struct in %s on line %d
