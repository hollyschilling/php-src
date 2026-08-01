--TEST--
Structs: a mutating implementation cannot satisfy a trait's uncolored abstract requirement
--FILE--
<?php
trait Contract {
    abstract public function step(): void;
}
struct S {
    use Contract;
    public int $n = 0;
    public function step() mutating: void { $this->n++; }
}
?>
--EXPECTF--
Fatal error: Mutating method S::step() cannot satisfy the non-mutating requirement Contract::step() in %s on line %d
