<?php
class PreVec<T> {
    protected array $items = [];
    public function push(T $v): void { $this->items[] = $v; }
    public function count(): int { return count($this->items); }
}
class PreMyVec<T> extends PreVec<T> {
    public function double(): int { return parent::count() * 2; }
}
/* Eager type references seed the preload closed-world sweep. */
function pre_seed(PreMyVec<int> $a): void {}
