--TEST--
Generics: param-dependent extends — constructor inheritance with promotion; statics per instantiation
--FILE--
<?php
declare(strict_types=1);

class P<T> {
    public function __construct(public T $val) {}
}
class C<T> extends P<T> {}

$c = new C<string>("hi");
var_dump($c->val);
try {
    new C<string>(5);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

class SB<T> {
    public static int $n = 0;
    public static function bump(): int { return ++static::$n; }
}
class SC<T> extends SB<T> {}

var_dump(SC<int>::bump());
var_dump(SC<int>::bump());
var_dump(SC<string>::bump()); // independent statics per instantiation chain
var_dump(SB<int>::bump());    // undeclared child slot aliases the grafted parent's (ordinary PHP semantics)
?>
--EXPECTF--
string(2) "hi"
P<string>::__construct(): Argument #1 ($val) must be of type string, int given, called in %s on line %d
int(1)
int(2)
int(1)
int(3)
