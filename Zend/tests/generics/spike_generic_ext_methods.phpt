--TEST--
SPIKE: generic methods in extensions targeting interfaces — the no-contract alternative to interface generic methods
--FILE--
<?php
declare(strict_types=1);

interface Repository {
    public function all(): array;
}

// A generic function declared against the interface via an extension:
// single implementation, no contract, no satisfaction checking.
extension RepoOps on Repository $repo {
    function wrapFirst<T>(): T {
        return new T($repo->all()[0]);
    }
}

class Orders implements Repository {
    public function all(): array { return [250, 999]; }
}
class Price {
    public function __construct(public int|float $v = 0) {}
}

$r = new Orders();
$p = $r->wrapFirst<Price>();
var_dump(get_class($p));
var_dump($p->v);

// The substituted return type is enforced on extension methods too.
extension BadOps on Repository $repo {
    function lieAs<T>(object $x): T { return $x; }
}
try {
    $r->lieAs<Price>(new stdClass());
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

// Structural errors stay precise through the extension path.
extension PlainOps on Repository $repo { function plain(): int { return 1; } }
try { $r->wrapFirst<Price,Price>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $r->plain<Price>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
string(5) "Price"
int(250)
BadOps::lieAs<Price>(): Return value must be of type Price, stdClass returned
Generic method Orders::wrapFirst() expects 1 type argument, 2 given
Method Orders::plain() is not generic
