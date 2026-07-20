<?php
class Vec<T> {
    private array $items = [];
    public function push(T $item): void { $this->items[] = $item; }
    public function pop(): mixed { return array_pop($this->items); }
}

class Repo {
    public ?Vec<int> $ints = null;
    public function set(Vec<string> $v): void {}
}

class FloatList extends Vec<float> {}

function totals(Vec<float> $v): void {}
function makeDates(): object { return new Vec<DateTime>(); }
function nested(Vec<Vec<int>> $vv): void {}
