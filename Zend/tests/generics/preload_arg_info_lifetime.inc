<?php
interface Container<T> {
    public function store(T $element): void;
}

class Holder<T> implements Container<T> {
    private array $items = [];
    public function store(T $element): void { $this->items[] = $element; }
    public function fetch(): mixed { return array_pop($this->items); }
}

/* Eight characters on purpose: whether the use-after-free this test covers
 * crashed depended on allocation sizes, and multiples of eight did. */
class Measured {}

function preload_closed_world(Holder<Measured> $held, Container<Measured> $c): void {}
