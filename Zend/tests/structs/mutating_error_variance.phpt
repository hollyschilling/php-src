--TEST--
Structs: a mutating method cannot satisfy an interface requirement (effect variance)
--FILE--
<?php
interface Advances {
    public function advance(): void;
}
struct Cursor implements Advances {
    public int $pos = 0;
    public mutating function advance(): void { $this->pos++; }
}
?>
--EXPECTF--
Fatal error: Mutating method Cursor::advance() cannot satisfy the non-mutating requirement Advances::advance() in %s on line %d
