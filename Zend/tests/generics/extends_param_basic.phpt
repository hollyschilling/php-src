--TEST--
Generics: param-dependent extends — inherit, override, parent::, typed methods, instanceof, independence
--FILE--
<?php

class Vec<T> {
    protected array $items = [];
    public function __construct(array $i = []) { $this->items = array_values($i); }
    public function push(T $v): void { $this->items[] = $v; }
    public function count(): int { return count($this->items); }
    public function first(): T { return $this->items[0]; }
}

class MyVec<T> extends Vec<T> {
    public function last(): T { return $this->items[count($this->items) - 1]; }
    public function count(): int { return parent::count() * 10; }
}

$v = new MyVec<int>([1, 2, 3]);
var_dump($v->count());   // override + parent::
var_dump($v->first());   // inherited from the grafted Vec<int>
var_dump($v->last());    // own method reading the protected parent property
$v->push(9);             // inherited, enforced with T = int
var_dump($v->last());

// The inherited signature is the parent instantiation's substituted one.
try {
    $v->push("nope");
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

var_dump($v instanceof Vec<int>);
var_dump(get_parent_class($v));

// A second instantiation binds independently.
$s = new MyVec<string>(["a"]);
$s->push("b");
var_dump($s->last());
var_dump($s instanceof Vec<string>);
var_dump($s instanceof Vec<int>);
?>
--EXPECTF--
int(30)
int(1)
int(3)
int(9)
Vec<int>::push(): Argument #1 ($v) must be of type int, string given, called in %s on line %d
bool(true)
string(8) "Vec<int>"
string(1) "b"
bool(true)
bool(false)
