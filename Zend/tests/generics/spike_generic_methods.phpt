--TEST--
SPIKE: generic methods with explicit type arguments — $seq->map<Price>($fn): Sequence<Price>
--FILE--
<?php
declare(strict_types=1);

// The typed closure wrapper: a plain generic struct-shaped class, no new
// engine work.
final class Func<TIn, TOut> {
    private Closure $fn;
    private function __construct(Closure $fn) { $this->fn = $fn; }
    public static function of(Closure $fn): static { return new static($fn); }
    public function __invoke(TIn $x): TOut { return ($this->fn)($x); }
}

class Sequence<T> {
    protected array $items = [];
    public function __construct(array $i = []) { $this->items = array_values($i); }
    public function count(): int { return count($this->items); }
    public function all(): array { return $this->items; }

    public function map<U>(object $f): Sequence<U> {
        $out = [];
        foreach ($this->items as $v) { $out[] = $f($v); }
        return new Sequence<U>($out);
    }
    public function single<U>(): U { return new U(); }
    public function pick<U>(U $x): U { return $x; }
}

class Order { public function __construct(public int $cents = 100) {} }
class Price { public function __construct(public float $eur = 0.0) {} }

$seq = new Sequence<Order>([new Order(250), new Order(999)]);
$toPrice = Func<Order, Price>::of(fn(Order $o) => new Price($o->cents / 100));

$prices = $seq->map<Price>($toPrice);
var_dump(get_class($prices));
var_dump($prices->count());
var_dump($prices->all()[1]->eur);
var_dump($prices instanceof Sequence<Price>);

// Func enforces the input side per call.
try { $toPrice(new Price()); } catch (TypeError $e) { echo "func-in ok\n"; }

// Substituted signatures enforce U in param and return positions.
try { $seq->pick<Price>(new Order()); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump(get_class($seq->single<Price>()));

// Structural errors are catchable and precise.
try { $seq->map<Price,Order>($toPrice); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $seq->count<Price>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $seq->nosuch<Price>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
string(15) "Sequence<Price>"
int(2)
float(9.99)
bool(true)
func-in ok
Sequence<Order>::pick<Price>(): Argument #1 ($x) must be of type Price, Order given, called in %s on line %d
string(5) "Price"
Generic method Sequence<Order>::map() expects 1 type argument, 2 given
Method Sequence<Order>::count() is not generic
Call to undefined method Sequence<Order>::nosuch()
