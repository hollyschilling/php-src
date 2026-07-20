--TEST--
Generics M4-lite: interface satisfaction is checked per instantiation
--FILE--
<?php
interface Collection<T> { public function add(T $x): void; }
class IntishVec<T> implements Collection<T> {
    public function add(int $x): void {} // satisfies the interface only when T = int
}
$ok = new IntishVec<int>();
echo "IntishVec<int> ok\n";
new IntishVec<string>();
?>
--EXPECTF--
IntishVec<int> ok

Fatal error: Declaration of IntishVec<string>::add(int $x): void must be compatible with Collection<string>::add(string $x): void in %s on line %d
