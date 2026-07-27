--TEST--
Generic methods: method type parameters nest inside composite type arguments
--FILE--
<?php
class Box<T> { public function __construct(public T $value) {} }
class Cell<T> extends Box<T> {}
class Pair<X, Y> { public function __construct(public X $first, public Y $second) {} }
interface Projector<I, O> { public function project(I $in): O; }

class Runner {
    public static function project<A, B, C>(Pair<A, C> $input, Projector<A, B> $projector): Pair<Box<B>, C> {
        return new Pair<Box<B>, C>(
            new Cell<B>($projector->project($input->first)),
            $input->second,
        );
    }
}

class Name { public function __construct(public string $s) {} }
class Length { public function __construct(public int $n) {} }
class NameLength implements Projector<Name, Length> {
    public function project(Name $in): Length { return new Length(strlen($in->s)); }
}

$out = Runner::project<Name, Length, int>(
    new Pair<Name, int>(new Name("holly"), 42), new NameLength());
echo get_class($out), "\n";
echo get_class($out->first), "\n";
var_dump($out->first->value->n, $out->second);

/* Mixed spaces: class param and method param nested in one signature. */
class Mix<T> {
    public function pack<U>(T $t, U $u): Pair<Box<T>, Box<U>> {
        return new Pair<Box<T>, Box<U>>(new Box<T>($t), new Box<U>($u));
    }
    public function viaClosure<U>(U $u): Pair<Box<Box<U>>, int> {
        $f = fn(): Pair<Box<Box<U>>, int>
            => new Pair<Box<Box<U>>, int>(new Box<Box<U>>(new Box<U>($u)), 1);
        return $f();
    }
}
$m = new Mix<DateTime>();
echo get_class($m->pack<ArrayObject>(new DateTime(), new ArrayObject())), "\n";
echo get_class($m->viaClosure<ArrayObject>(new ArrayObject())), "\n";

/* Wrong instantiation is rejected with the substituted nested name. */
class Bad {
    public static function make<B>(): Pair<Box<B>, int> {
        return new Pair<Box<string>, int>(new Box<string>("x"), 1);
    }
}
try {
    Bad::make<DateTime>();
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

/* Multi-instantiation churn: two-level clones destruct cleanly in any order. */
$m2 = new Mix<DateTimeImmutable>();
for ($i = 0; $i < 50; $i++) {
    $m->pack<SplStack>(new DateTime(), new SplStack());
    $m2->pack<ArrayObject>(new DateTimeImmutable(), new ArrayObject());
    $m2->viaClosure<SplStack>(new SplStack());
}
echo "done\n";
?>
--EXPECT--
Pair<Box<Length>,int>
Cell<Length>
int(5)
int(42)
Pair<Box<DateTime>,Box<ArrayObject>>
Pair<Box<Box<ArrayObject>>,int>
Bad::make<DateTime>(): Return value must be of type Pair<Box<DateTime>,int>, Pair<Box<string>,int> returned
done
