--TEST--
Generics packs: spread into deferred implements and extends references
--FILE--
<?php
declare(strict_types=1);

interface Merger<A, B> { public function merge(A $a, B $b): string; }
class Zip<...Ts> implements Merger<...Ts> {
    public function merge(int $a, string $b): string { return "$a:$b"; }
}
$z = new Zip<int, string>();
var_dump($z->merge(1, "x"));
var_dump($z instanceof Merger<int,string>);

class BasePack<A, B> {
    public function pair(A $a, B $b): array { return [$a, $b]; }
}
class ExtPack<...Ts> extends BasePack<...Ts> {}
$e = new ExtPack<string, int>();
var_dump($e->pair("k", 3));
var_dump($e instanceof BasePack<string,int>);

// The spread contract is checked per instantiation with substituted types:
// a spread that flips the interface's arguments fails like any incompatible
// implementation.
new Zip<string, int>();
?>
--EXPECTF--
string(3) "1:x"
bool(true)
array(2) {
  [0]=>
  string(1) "k"
  [1]=>
  int(3)
}
bool(true)

Fatal error: Declaration of Zip<string,int>::merge(int $a, string $b): string must be compatible with Merger<string,int>::merge(string $a, int $b): string in %s on line %d
