--TEST--
Structs: set hooks are implicitly mutating; get hooks stay pure reads
--FILE--
<?php

// A set hook binds $this borrowed-exclusive, so it may call mutating
// methods; no syntax is involved -- setters mutate by definition.
struct Temp {
    public int $writes = 0;
    public function bump() mutating: void { $this->writes++; }
    public float $celsius = 0.0 {
        set {
            $this->celsius = $value;
            $this->bump();
        }
    }
}

$t = new Temp();
$t->celsius = 21.5;
$t->celsius = 22.0;
var_dump($t->celsius, $t->writes);

// Copy-on-write composes: the hooked write separates the shared value first,
// and the hook's nested mutation lands in the separated copy.
$a = new Temp();
$b = $a;
$b->celsius = 9.0;
var_dump($a->celsius, $a->writes, $b->celsius, $b->writes);

// A get hook is a pure read: nested mutating calls are rejected.
struct Probe {
    public int $n = 0;
    public function bump() mutating: void { $this->n++; }
    public int $v {
        get {
            $this->bump();
            return 1;
        }
    }
}
$p = new Probe();
try { $x = $p->v; } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump($p->n);

// Trait-provided set hooks get the flag on the struct's copy...
trait Hooked {
    public int $w = 0 {
        set {
            $this->w = $value;
            $this->count();
        }
    }
}
struct Counted {
    public int $calls = 0;
    public function count() mutating: void { $this->calls++; }
    use Hooked;
}
$s = new Counted();
$s->w = 5;
var_dump($s->w, $s->calls);

// ...while the same trait keeps serving classes untouched.
class Plain {
    use Hooked;
    public int $calls = 0;
    public function count(): void { $this->calls++; }
}
$c = new Plain();
$c->w = 4;
var_dump($c->w, $c->calls);

?>
--EXPECT--
float(22)
int(2)
float(0)
int(0)
float(9)
int(1)
Cannot call mutating method Probe::bump() on $this in a non-mutating method
int(0)
int(5)
int(1)
int(4)
int(1)
