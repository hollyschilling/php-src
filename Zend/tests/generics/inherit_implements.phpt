--TEST--
Generics M3: classes implement generic interfaces with concrete args; variance uses substituted signatures
--FILE--
<?php
interface Collection<T> {
    public function add(T $item): void;
    public function first(): ?T;
}

class IntBag implements Collection<int> {
    private array $items = [];
    public function add(int $item): void { $this->items[] = $item; }
    public function first(): ?int { return $this->items[0] ?? null; }
}

$b = new IntBag();
$b->add(3);
var_dump($b instanceof Collection<int>);
var_dump($b->first());

function drain(Collection<int> $c): ?int { return $c->first(); }
var_dump(drain($b));

interface IntCollection extends Collection<int> {}
class Impl implements IntCollection {
    public function add(int $item): void {}
    public function first(): ?int { return null; }
}
var_dump(new Impl() instanceof Collection<int>);
?>
--EXPECT--
bool(true)
int(3)
int(3)
bool(true)
