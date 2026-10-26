<?php
/**
 * A small generic collection library: a generic interface, a generic class
 * implementing it, and a generic subclass — the shapes a real library built on
 * this branch would use (type-parameterised members, `parent::`, `new static()`
 * propagating the instantiation, a built-in non-generic interface in the
 * hierarchy).
 *
 * lib_plain.php is the hand-written non-generic twin: STRUCTURALLY IDENTICAL,
 * same class names, same method bodies, same order, with the element type fixed
 * to `int`. The only difference a benchmark measures between them is the
 * generics. If you edit one, edit the other.
 */
declare(strict_types=1);

interface Collection<T> extends Countable, IteratorAggregate
{
    public function add(T $item): void;
    public function contains(T $item): bool;
    public function first(): T;
}

class Vec<T> implements Collection<T>
{
    protected array $items;

    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    public function add(T $item): void
    {
        $this->items[] = $item;
    }

    public function contains(T $item): bool
    {
        return in_array($item, $this->items, true);
    }

    public function first(): T
    {
        return $this->items[0];
    }

    public function last(): T
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

class SortedVec<T> extends Vec<T>
{
    public function add(T $item): void
    {
        parent::add($item);
        sort($this->items);
    }

    public function median(): T
    {
        return $this->items[intdiv(count($this->items), 2)];
    }
}
