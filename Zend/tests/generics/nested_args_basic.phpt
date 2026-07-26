--TEST--
Generics: type parameters nest inside composite type arguments (class space)
--FILE--
<?php
class Box<T> { public function __construct(public T $value) {} }
class Pair<X, Y> { public function __construct(public X $first, public Y $second) {} }

class Wrap<B> {
    public Pair<Box<B>, int> $slot;
    public function make(int $n, B $v): Pair<Box<B>, int> {
        return new Pair<Box<B>, int>(new Box<B>($v), $n);
    }
    public function deep(B $v): Pair<Box<Box<B>>, int> {
        return new Pair<Box<Box<B>>, int>(new Box<Box<B>>(new Box<B>($v)), 9);
    }
    public function accepts(Pair<Box<B>, int> $p): string {
        return get_class($p);
    }
    public function probe(object $o): bool {
        return $o instanceof Pair<Box<B>, int>;
    }
}

$w = new Wrap<DateTime>();
$p = $w->make(7, new DateTime('2026-07-26'));
echo get_class($p), "\n";
$w->slot = $p;
echo $w->accepts($p), "\n";
var_dump($w->probe($p));
echo get_class($w->deep(new DateTime())), "\n";

/* Scalars substitute into nested positions. */
$wi = new Wrap<string>();
echo get_class($wi->make(1, "s")), "\n";

/* Exact enforcement of the substituted nested type. */
try {
    $w->accepts($wi->make(1, "s"));
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
try {
    $w->slot = $wi->make(1, "s");
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
Pair<Box<DateTime>,int>
Pair<Box<DateTime>,int>
bool(true)
Pair<Box<Box<DateTime>>,int>
Pair<Box<string>,int>
Wrap<DateTime>::accepts(): Argument #1 ($p) must be of type Pair<Box<DateTime>,int>, Pair<Box<string>,int> given, called in %s on line %d
Cannot assign Pair<Box<string>,int> to property Wrap<DateTime>::$slot of type Pair<Box<DateTime>,int>
