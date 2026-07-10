--TEST--
Surfaces: interface-reachable members are never gated (and must be bound or public)
--FILE--
<?php
// 1. A surfaced method satisfying an ordinarily-implemented (unbound)
//    interface is a link-time error.
$code = 'interface W { public function emit(): string; }
class Node implements W { surface Compiler; surface[Compiler] function emit(): string { return "x"; } }';
$out = shell_exec(PHP_BINARY . ' -d display_errors=1 -r ' . escapeshellarg($code) . ' 2>&1');
echo trim((string) $out), "\n\n";

// 2. Bound: explicit implements + binding coexist (single interface entry),
//    and the member is reachable both directly and interface-typed with no
//    grant anywhere in this file.
interface Walkable { public function emit(): string; }
abstract class BaseNode { abstract public function emit(): string; }
class Node extends BaseNode implements Walkable {
    surface Compiler implements Walkable;
    surface[Compiler] function emit(): string { return "emitted"; }
    surface[Compiler] function reset(): void {}     // not in the interface: stays gated
}

$n = new Node();
echo $n->emit(), "\n";
function walk(Walkable $w): string { return $w->emit(); }
echo walk($n), "\n";
echo implode(',', (new ReflectionClass('Node'))->getInterfaceNames()), "\n";
try { $n->reset(); } catch (Error $e) { echo "reset: gated\n"; }

// 3. An interface __construct constrains the signature only; a gated
//    constructor stays gated.
interface HasCtor { public function __construct(string $s); }
class Gated implements HasCtor {
    surface Creation;
    surface[Creation] function __construct(public string $s) {}
}
try { new Gated("x"); } catch (Error $e) { echo "ctor: gated\n"; }
function make(): Gated { use Gated with surface[Creation]; return new Gated("ok"); }
echo make()->s, "\n";
?>
--EXPECTF--
Fatal error: Method Node::emit() satisfies interface W but is not on a surface bound to it (an interface-reachable member must be public or on a surface bound to that interface) in Command line code on line 2
Stack trace:
#0 {main}

emitted
emitted
Walkable
reset: gated
ctor: gated
ok
