--TEST--
Composite (DNF) arguments at method call sites via turbofish
--FILE--
<?php declare(strict_types=1);
interface B {} interface C {}
class D implements B, C {}
class A {}

class Seq {
    public function name<U>(): string { return U::class; }
    public static function stat<U>(): string { return U::class; }
    public function mk<U>(mixed $seed): object { return new Vec::<U>($seed); }
}
class Vec<T> { public function __construct(public mixed $seed) {} }

$s = new Seq();

// composite type arguments require the explicit form at call sites
var_dump($s->name::<int|string>());
var_dump(Seq::stat::<A|(B&C)>());
var_dump($s->name::<B&C>());

// composite arg flows into a body reference (new Vec<U>)
var_dump($s->mk::<A|null>(new A)::class);

// plain '<' with a composite shape at a call site stays unclaimed:
// '$s->name<int|string>()' parses as comparisons and fails loudly
try {
    eval('$s->name<int|string>();');
    echo "no error\n";
} catch (Throwable $e) {
    echo get_class($e), "\n";
}
?>
--EXPECT--
string(10) "int|string"
string(7) "(B&C)|A"
string(3) "B&C"
string(11) "Vec<A|null>"
ParseError
