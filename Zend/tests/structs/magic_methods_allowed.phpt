--TEST--
Structs: pure-read magic methods are permitted and bind $this by value
--FILE--
<?php

// __toString satisfies Stringable, including via string interpolation and
// Stringable-typed parameters.
struct Money {
    public function __construct(public int $amount, public string $currency) {}
    public function __toString(): string { return $this->amount . ' ' . $this->currency; }
}
$m = new Money(100, 'USD');
var_dump($m instanceof Stringable, (string) $m, "price: $m");
function render(Stringable $s): string { return (string) $s; }
var_dump(render($m));

// A trait-supplied __toString composes and still yields Stringable.
trait Described { public function __toString(): string { return 'P(' . $this->x . ')'; } }
struct P { use Described; public function __construct(public int $x) {} }
var_dump((string) new P(5), (new P(6)) instanceof Stringable);

// __invoke binds $this by value: each invocation operates on a fresh copy,
// so mutations are local to the call.
struct Acc {
    public function __construct(public int $n) {}
    public function __invoke(int $v): int { $this->n += $v; return $this->n; }
}
$a = new Acc(10);
var_dump($a(5), $a(3), $a->n);

// __debugInfo controls var_dump presentation.
struct Pt {
    public function __construct(public float $x, public float $y) {}
    public function __debugInfo(): array { return ['coords' => "($this->x, $this->y)"]; }
}
var_dump(new Pt(1.5, 2.5));

// __call sees its writes within the call and discards them at return;
// __callStatic has no receiver at all.
struct C {
    public function __construct(public int $x) {}
    public function __call(string $m, array $args): string { $this->x = 999; return "$m/{$this->x}"; }
    public static function __callStatic(string $m, array $args): string { return "static:$m"; }
}
$c = new C(1);
var_dump($c->anything(), $c->x, C::stat());

?>
--EXPECTF--
bool(true)
string(7) "100 USD"
string(14) "price: 100 USD"
string(7) "100 USD"
string(4) "P(5)"
bool(true)
int(15)
int(13)
int(10)
object(Pt)#%d (1) {
  ["coords"]=>
  string(10) "(1.5, 2.5)"
}
string(12) "anything/999"
int(1)
string(11) "static:stat"
