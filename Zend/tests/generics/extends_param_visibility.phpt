--TEST--
Generics: param-dependent extends — grafted parent methods keep the parent instantiation's scope
--FILE--
<?php

class VB<T> {
    private array $store = [];
    private function log(T $v): void { $this->store[] = $v; }
    public function add(T $v): void { $this->log($v); }
    public function count(): int { return count($this->store); }
}
class VC<T> extends VB<T> {
    public function tryTouch(): void { $this->log(1); }
}

$v = new VC<int>();
$v->add(1);
$v->add(2);
var_dump($v->count());

// Child scope is denied the parent's private, with the parent instantiation
// named as declaring scope.
try {
    $v->tryTouch();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

class PB<T> { protected function helper(): string { return "prot"; } }
class PC<T> extends PB<T> { public function go(): string { return $this->helper(); } }
var_dump((new PC<int>)->go());
?>
--EXPECT--
int(2)
Call to private method VB<int>::log() from scope VC<int>
string(4) "prot"
