--TEST--
Surfaces: Reflection API and reflection bypass
--FILE--
<?php
interface I { public function a(): string; }
class P { surface Legacy; }
class Q extends P {
    surface Prod implements I;
    surface T;
    surface[Prod] function a(): string { return "invoked"; }
    surface[T] int $w = 1;
    surface[T] const C = 2;
}

$rc = new ReflectionClass('Q');
$names = $rc->getSurfaceNames();
sort($names);
var_dump($names);
var_dump($rc->hasSurface('Legacy'));    // inherited
var_dump($rc->hasSurface('Nope'));
var_dump($rc->getSurfaceInterface('Prod'));
var_dump($rc->getSurfaceInterface('T'));
try { $rc->getSurfaceInterface('Nope'); } catch (ReflectionException $e) { echo $e->getMessage(), "\n"; }

var_dump($rc->getMethod('a')->getSurfaceNames());
var_dump($rc->getProperty('w')->getSurfaceNames());
var_dump((new ReflectionClassConstant('Q', 'C'))->getSurfaceNames());

// A plain public member is on no surface.
class Plain { public function f() {} }
var_dump((new ReflectionMethod('Plain', 'f'))->getSurfaceNames());

// Reflection-based invocation bypasses surfaces, as it bypasses visibility.
var_dump((new ReflectionMethod('Q', 'a'))->invoke(new Q()));
var_dump($rc->getInterfaceNames()); // surface-bound interfaces are nominal
?>
--EXPECT--
array(3) {
  [0]=>
  string(6) "Legacy"
  [1]=>
  string(4) "Prod"
  [2]=>
  string(1) "T"
}
bool(true)
bool(false)
string(1) "I"
NULL
Surface Nope does not exist
array(1) {
  [0]=>
  string(4) "Prod"
}
array(1) {
  [0]=>
  string(1) "T"
}
array(1) {
  [0]=>
  string(1) "T"
}
array(0) {
}
string(7) "invoked"
array(1) {
  [0]=>
  string(1) "I"
}
