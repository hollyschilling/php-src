<?php
declare(strict_types=1);
class MGPrice { public function __construct(public int $v = 0) {} }
class MGSeq<T> {
    public function __construct(private array $items = []) {}
    public static function of<U>(mixed ...$vals): MGSeq<U> { return new MGSeq<U>($vals); }
    public function fill(int $n): static { $this->items = range(1, $n); return $this; }
    public function count(): int { return count($this->items); }
    public function name<U>(): string { return U::class; }
    public function map<U>(callable $f): MGSeq<U> {
        $out = [];
        foreach ($this->items as $v) { $out[] = $f($v); }
        return new MGSeq<U>($out);
    }
}
