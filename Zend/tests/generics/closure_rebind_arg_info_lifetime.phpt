--TEST--
Generics: rebinding a closure with a substituted signature keeps sole ownership of its arg_info
--FILE--
<?php
// A closure declared inside a generic instantiation gets a substituted,
// concrete signature at creation. Rebinding it (Closure::bind / bindTo) copies
// the op_array header verbatim, which used to share the substituted arg_info
// block and its ownership flag with the original — so destroying the copies
// released the same strings and types more than once. Each copy must own its
// block instead.

class Vec<T> {
    public function mapper(): Closure {
        // The parameter's doc comment is a non-interned heap string owned by
        // the substituted block; an over-release of it is a use-after-free.
        return function (/** the element */ T $x): T { return $x; };
    }
}

class Plain {}

$v = new Vec<int>();
$c = $v->mapper();

// The rebound copy keeps the concrete signature and enforces it.
$d = Closure::bind($c, new Plain());
var_dump((string) (new ReflectionFunction($d))->getParameters()[0]->getType());
var_dump((string) (new ReflectionFunction($d))->getReturnType());
var_dump($d(7));
try { $d("no"); } catch (TypeError $e) { echo "enforced\n"; }

// bindTo, and rebinding an already-rebound closure, both stay independent.
$e = $c->bindTo(new Plain());
$f = $d->bindTo(new Plain());
var_dump($e(8), $f(9));

// Fan a substituted-signature closure out into many copies and tear them all
// down: with shared ownership this over-releases the doc-comment string.
$copies = [];
for ($i = 0; $i < 50; $i++) {
    $copies[] = Closure::bind($c, new Plain());
}
unset($copies, $c, $d, $e, $f);
gc_collect_cycles();

// Churn the allocator so a prematurely freed string would be visibly reused.
$junk = [];
for ($i = 0; $i < 1000; $i++) { $junk[] = str_repeat('z', 17); }

echo "ok\n";
?>
--EXPECT--
string(3) "int"
string(3) "int"
int(7)
enforced
int(8)
int(9)
ok
