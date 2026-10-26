--TEST--
Surfaces + extension methods: existence-based shadowing; extensions on surface-bound interfaces
--FILE--
<?php
class Box {
    surface S;
    surface[S] function peek(): string { return "surface-peek"; }
}

// Extension methods live on the method-miss path: an existing surface member
// is never shadowed by an extension — ungranted access errors out instead.
extension Box $b {
    function peek(): string { return "ext-peek"; }
    function extra(): string { return "ext-extra"; }
}

$x = new Box();
try { $x->peek(); } catch (Error $e) { echo "peek: denied (extension does not shadow)\n"; }
echo $x->extra(), "\n";

function granted(Box $x): void {
    use Box with surface[S];
    echo $x->peek(), "\n";
}
granted($x);

// A surface-bound interface is a real (nominal) implementation, so an
// extension targeting the interface reaches the object without any grant.
interface Prod { public function ingest(string $v): void; }
class Q {
    surface P implements Prod;
    private array $i = [];
    surface[P] function ingest(string $v): void { $this->i[] = $v; }
}
extension Prod $p {
    function pump(): string { return "pumped-via-interface"; }
}
echo (new Q)->pump(), "\n";
?>
--EXPECT--
peek: denied (extension does not shadow)
ext-extra
surface-peek
pumped-via-interface
