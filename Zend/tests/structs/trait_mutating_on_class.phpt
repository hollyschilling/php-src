--TEST--
A trait's mutating method works on class consumers with the marker ignored
--FILE--
<?php
trait Bumper {
    public function bump() mutating: void { $this->n += 1; }
}
struct Counter { use Bumper; public function __construct(public int $n = 0) {} }
class Tally    { use Bumper; public int $n = 0; }

$s = new Counter(5);
$t = $s;              // struct: value copy
$s->bump();
var_dump($s->n);
var_dump($t->n);

$c = new Tally();
$d = $c;              // class: reference copy
$c->bump();
var_dump($c->n);
var_dump($d->n);
?>
--EXPECT--
int(6)
int(5)
int(1)
int(1)
