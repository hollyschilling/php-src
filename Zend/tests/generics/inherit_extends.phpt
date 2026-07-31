--TEST--
Generics M3: classes extend generic classes with concrete args, inheriting enforcement
--FILE--
<?php
class Vec<T> implements Countable {
    protected array $items = [];
    public function push(T $item): void { $this->items[] = $item; }
    public function count(): int { return count($this->items); }
}

class IntList extends Vec<int> {
    public function sum(): int { return array_sum($this->items); }
}

$l = new IntList();
$l->push(4);
$l->push(5);
var_dump($l->sum());
var_dump(count($l));
try { $l->push("x"); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump($l instanceof Vec<int>);
var_dump($l instanceof Countable);

function total(Vec<int> $v): int { return $v->count(); }
var_dump(total($l));
var_dump(total(new Vec<int>()));
?>
--EXPECTF--
int(9)
int(2)
Vec<int>::push(): Argument #1 ($item) must be of type int, string given, called in %s on line %d
bool(true)
bool(true)
int(2)
int(0)
