--TEST--
Generics M3: variance checks run against substituted signatures
--FILE--
<?php
interface Collection<T> { public function add(T $item): void; }
class Wrong implements Collection<int> {
    public function add(string $item): void {}
}
?>
--EXPECTF--
Fatal error: Declaration of Wrong::add(string $item): void must be compatible with Collection<int>::add(int $item): void in %s on line %d
