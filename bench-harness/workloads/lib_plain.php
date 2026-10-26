<?php
/**
 * Non-generic twin of lib_generic.php: what you would hand-write today, with
 * the element type fixed to `int`.
 *
 * The two files are kept STRUCTURALLY IDENTICAL — same class names, same method
 * bodies, same order — so the only difference a benchmark can measure is the
 * generics themselves. If you edit one, edit the other.
 */
declare(strict_types=1);

interface Collection extends Countable, IteratorAggregate
{
    public function add(int $item): void;
    public function contains(int $item): bool;
    public function first(): int;
}

class Vec implements Collection
{
    protected array $items;

    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    public function add(int $item): void
    {
        $this->items[] = $item;
    }

    public function contains(int $item): bool
    {
        return in_array($item, $this->items, true);
    }

    public function first(): int
    {
        return $this->items[0];
    }

    public function last(): int
    {
        return $this->items[count($this->items) - 1];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function map(Closure $fn): static
    {
        return new static(array_map($fn, $this->items));
    }

    public function filter(Closure $fn): static
    {
        return new static(array_filter($this->items, $fn));
    }

    public function slice(int $offset, int $length): static
    {
        return new static(array_slice($this->items, $offset, $length));
    }

    public function toArray(): array
    {
        return $this->items;
    }
}

class SortedVec extends Vec
{
    public function add(int $item): void
    {
        parent::add($item);
        sort($this->items);
    }

    public function median(): int
    {
        return $this->items[intdiv(count($this->items), 2)];
    }
}
