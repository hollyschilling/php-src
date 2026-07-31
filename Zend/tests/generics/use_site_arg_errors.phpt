--TEST--
Generics: formerly-restricted param-dependent composite members now substitute
--FILE--
<?php declare(strict_types=1);
class Box<T> { public function __construct() {} }
class Vec<T> { public function __construct() {} }

// all three formerly-rejected shapes, now first-class:
class Pair<T> {
    public ?Vec<T|null> $v = null;                                // composite arg member
    public function h(Vec<T>|ArrayObject $p): string {            // symbolic union member
        return get_debug_type($p);
    }
    public function f(): Box<Box<T>>|Countable {                  // nested, in a union
        return new Box<Box<T>>();
    }
}
class Foo {}
$p = new Pair<Foo>();
$p->v = new Vec<Foo|null>();
var_dump($p->v::class);
var_dump($p->h(new Vec<Foo>()));
var_dump($p->h(new ArrayObject()));
try { $p->h(new Vec<Box<Foo>>()); } catch (TypeError $e) { echo "invariance holds\n"; }
var_dump($p->f()::class);

// deferred inheritance references remain bare-only (the one kept restriction)
?>
--EXPECT--
string(13) "Vec<Foo|null>"
string(8) "Vec<Foo>"
string(11) "ArrayObject"
invariance holds
string(13) "Box<Box<Foo>>"
