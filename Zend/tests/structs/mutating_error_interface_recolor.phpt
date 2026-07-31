--TEST--
Structs: an interface override may not add the mutating color
--FILE--
<?php
interface Reader {
    public function advance(): void;
}
interface Recolored extends Reader {
    public function advance() mutating: void;
}
?>
--EXPECTF--
Fatal error: Mutating method Recolored::advance() cannot satisfy the non-mutating requirement Reader::advance() in %s on line %d
