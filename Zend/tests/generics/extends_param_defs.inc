<?php
class OpcVec<T> {
    protected array $items = [];
    public function __construct(array $i = []) { $this->items = array_values($i); }
    public function push(T $v): void { $this->items[] = $v; }
    public function sum(): int { return (int) array_sum($this->items); }
}
class OpcMyVec<T> extends OpcVec<T> {
    public function total(): int { return parent::sum(); }
}
