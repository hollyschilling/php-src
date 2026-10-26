--TEST--
Generics packs: mid-position pack — post-pack params bind right-to-left; T-fetch and types remap
--FILE--
<?php
declare(strict_types=1);

class Pair<...Tp, R> {
    public R $result;
    public function __construct(R $r) { $this->result = $r; }
    public function cls(): string { return R::class; }
    public function make(): R { return new R(); }
}
class Foo { public string $tag = "foo"; }

// pack = [int, string], R = Foo
$p = new Pair<int, string, Foo>(new Foo());
var_dump($p->cls());
var_dump($p->make()->tag);

// pack = [int], R = Foo — R still binds to the last argument
$q = new Pair<int, Foo>(new Foo());
var_dump($q->cls());

// The substituted signature names the instantiation's R.
try {
    new Pair<int, Foo>(5);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

// Distinct arities are distinct classes.
var_dump(Pair<int,Foo>::class);
var_dump(Pair<int,string,Foo>::class);
?>
--EXPECTF--
string(3) "Foo"
string(3) "foo"
string(3) "Foo"
Pair<int,Foo>::__construct(): Argument #1 ($r) must be of type Foo, int given, called in %s on line %d
string(13) "Pair<int,Foo>"
string(20) "Pair<int,string,Foo>"
