--TEST--
Generics M2: new Vec<T>() stamps a monomorphized instantiation with enforced types
--FILE--
<?php
class Vec<T> {
    private array $items = [];
    public function push(T $item): void { $this->items[] = $item; }
    public function pop(): ?T { return array_pop($this->items); }
    public function count(): int { return count($this->items); }
}

$v = new Vec<stdClass>();
var_dump(get_class($v));
$v->push(new stdClass);
$v->push(new stdClass);
var_dump($v->count());
var_dump(get_class($v->pop()));

try {
    $v->push(new DateTime());
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

$ints = new Vec<int>();
$ints->push(42);
try { $ints->push([]); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump($ints->pop());

var_dump(get_class($ints) === get_class($v));
var_dump($v instanceof Vec<stdClass>);
var_dump($ints instanceof Vec<stdClass>);
?>
--EXPECTF--
string(13) "Vec<stdClass>"
int(2)
string(8) "stdClass"
Vec<stdClass>::push(): Argument #1 ($item) must be of type stdClass, DateTime given, called in %s on line %d
Vec<int>::push(): Argument #1 ($item) must be of type int, array given, called in %s on line %d
int(42)
bool(false)
bool(true)
bool(false)
