--TEST--
Structs: unset and every reference into a struct's interior are runtime errors
--FILE--
<?php

struct P {
    public function __construct(public int $x, public array $arr = []) {}
    public function refThis(): void { $r = &$this->x; }
}

$a = new P(1);
$b = $a;

// unset: the shape is fixed; no slot has an absent state. Nothing mutates.
try { unset($a->x); } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump(isset($a->x), isset($b->x));

// foreach by reference takes references into the property slots.
try { foreach ($a as &$v) {} } catch (Error $e) { echo $e->getMessage(), "\n"; }

// By-value foreach works as for any object.
foreach ($a as $k => $v) { if ($k === 'x') var_dump($v); }

// Plain reference acquisition, on a variable and on $this.
try { $r = &$a->x; } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $a->refThis(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Assigning a reference into a property, constant and dynamic name.
$ref = 5;
try { $a->x = &$ref; } catch (Error $e) { echo $e->getMessage(), "\n"; }
$n = 'x';
try { $a->{$n} = &$ref; } catch (Error $e) { echo $e->getMessage(), "\n"; }

// By-reference arguments: internal, known userland, and dynamic callees.
$s = new P(0, [3, 1, 2]);
try { sort($s->arr); } catch (Error $e) { echo $e->getMessage(), "\n"; }
function takesRef(array &$x) { $x[] = 9; }
try { takesRef($s->arr); } catch (Error $e) { echo $e->getMessage(), "\n"; }
$fn = 'takesRef';
try { $fn($s->arr); } catch (Error $e) { echo $e->getMessage(), "\n"; }
var_dump($s->arr);

// Struct *variables* may still be referenced: the reference names the
// storage slot, not the value's interior (the sort($array) bargain).
function reinit(P &$p): void { $p = new P(42); }
reinit($a);
var_dump($a->x, $b->x);

?>
--EXPECT--
Cannot unset struct property P::$x
bool(true)
bool(true)
Cannot iterate struct P by reference
int(1)
Cannot take reference to struct property P::$x
Cannot take reference to struct property P::$x
Cannot assign by reference to struct property P::$x
Cannot assign by reference to struct property P::$x
Cannot take reference to struct property P::$arr
Cannot take reference to struct property P::$arr
Cannot take reference to struct property P::$arr
array(3) {
  [0]=>
  int(3)
  [1]=>
  int(1)
  [2]=>
  int(2)
}
int(42)
int(1)
