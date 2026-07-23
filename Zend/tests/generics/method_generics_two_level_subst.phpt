--TEST--
Generic methods: signatures substituted at BOTH class- and method-stamp level (regression: arena arg_info freed as heap)
--DESCRIPTION--
A generic method whose parameter type is a generic instantiation mentioning
both the class parameter and the method parameter is cloned twice: class
stamping substitutes V, method stamping then substitutes W over the already
substituted array. The method clone must stash the TEMPLATE's real arg_info as
its hidden original, not the class clone's arena block -- otherwise the final
release efree()s an arena pointer and the heap is corrupted at shutdown.
--FILE--
<?php
declare(strict_types=1);

class Func<...TArgs, TReturn> {
    private Closure $fn;
    public function __construct(callable $fn) { $this->fn = $fn(...); }
    public function invoke(mixed ...$a): TReturn { return ($this->fn)(...$a); }
}
class Seq<T> {
    public function __construct(public array $i = []) {}
}

class D<V> {
    public function bothParams<W>(Func<V, W> $f): Seq<W> { return new Seq<W>([$f->invoke(1)]); }
    public function classOnly<W>(Func<V, int> $f): Seq<W> { return new Seq<W>([]); }
    public function methodOnly<W>(Func<int, W> $f): Seq<W> { return new Seq<W>([$f->invoke(1)]); }
    public function plain(Func<string, V, bool> $f): bool { return true; }
}

$d = new D<int>();

// The corrupting shape, plus the three that always worked.
var_dump($d->bothParams<string>(new Func<int, string>(fn(int $v): string => "a"))->i);
var_dump($d->classOnly<string>(new Func<int, int>(fn(int $v): int => $v))->i);
var_dump($d->methodOnly<string>(new Func<int, string>(fn(int $v): string => "b"))->i);
var_dump($d->plain(new Func<string, int, bool>(fn(string $s, int $i): bool => true)));

// Several instantiations of one method over one class clone, and a second
// class instantiation, so every combination of clone levels is destroyed.
var_dump($d->bothParams<float>(new Func<int, float>(fn(int $v): float => 1.5))->i);
$e = new D<int>();
var_dump($e->bothParams<int>(new Func<int, int>(fn(int $v): int => 7))->i);

// The doubly substituted signature is enforced with both parameters resolved.
try {
    $d->bothParams<string>(new Func<int, int>(fn(int $v): int => $v));
} catch (TypeError $t) {
    echo $t->getMessage(), "\n";
}
echo "done\n";
?>
--EXPECTF--
array(1) {
  [0]=>
  string(1) "a"
}
array(0) {
}
array(1) {
  [0]=>
  string(1) "b"
}
bool(true)
array(1) {
  [0]=>
  float(1.5)
}
array(1) {
  [0]=>
  int(7)
}
D<int>::bothParams<string>(): Argument #1 ($f) must be of type Func<int,string>, Func<int,int> given, called in %s on line %d
done
