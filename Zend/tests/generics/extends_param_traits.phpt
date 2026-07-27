--TEST--
Generics: param-dependent extends — traits flatten into the parentless template before the graft
--FILE--
<?php

trait Named {
    public string $name = "anon";
    public function label(): string { return $this->name . ":" . $this->tag(); }
}

class TB<T> {
    public function tag(): string { return "base"; }
    public function greet(): string { return "hi"; }
}
class TC<T> extends TB<T> {
    use Named;
    public function tag(): string { return "child"; }  // trait calls the override
}

$t = new TC<int>();
var_dump($t->label());   // trait method, dispatching to the child override
var_dump($t->greet());   // inherited from the grafted parent
$t->name = "x";
var_dump($t->label());
?>
--EXPECT--
string(10) "anon:child"
string(2) "hi"
string(7) "x:child"
