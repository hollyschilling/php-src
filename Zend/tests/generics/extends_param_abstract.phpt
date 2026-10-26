--TEST--
Generics: param-dependent extends — abstract parents: satisfied by the template, or fatal per instantiation
--FILE--
<?php

abstract class AB<T> {
    abstract public function get(): T;
    public function describe(): string { return "has " . var_export($this->get(), true); }
}
class AC<T> extends AB<T> {
    public function __construct(private T $v) {}
    public function get(): T { return $this->v; }
}

$a = new AC<int>(7);
var_dump($a->get());
var_dump($a->describe());

// Unsatisfied abstract methods fatal at stamp time, like ordinary linking.
abstract class AB2<T> { abstract public function need(T $x): void; }
class AC2<T> extends AB2<T> {}
new AC2<int>();
?>
--EXPECTF--
int(7)
string(5) "has 7"

Fatal error: Class AC2<int> contains 1 abstract method and must therefore be declared abstract or implement the remaining method (AB2<int>::need) in %s on line %d
