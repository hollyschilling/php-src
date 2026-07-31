--TEST--
Surfaces: hierarchy auto-hold, grants across the hierarchy (runtime-class semantics)
--FILE--
<?php
class Base {
    surface W;
    surface[W] function write(): string { return "wrote"; }

    public function selfUse(): string { return $this->write(); } // auto-hold
}
class Child extends Base {
    public function childUse(): string { return $this->write(); } // inherited hold
}

$c = new Child();
echo $c->selfUse(), "\n";
echo $c->childUse(), "\n";
try { $c->write(); } catch (Error $e) { echo "outside: denied\n"; }

// Grant on the base covers subtype receivers.
function viaBase(Base $b): string {
    use Base with surface[W];
    return $b->write();
}
echo viaBase(new Child()), "\n";

// Grant on the subtype: the runtime check keys on the receiver's runtime
// class (a Child instance is covered even if statically typed Base — a
// documented deviation from the RFC's static-type rule).
function viaChild(Base $b): string {
    use Child with surface[W];
    try { return $b->write() . " (" . get_class($b) . ")"; }
    catch (Error $e) { return "denied (" . get_class($b) . ")"; }
}
echo viaChild(new Base()), "\n";
echo viaChild(new Child()), "\n";
?>
--EXPECT--
wrote
wrote
outside: denied
wrote
denied (Base)
wrote (Child)
