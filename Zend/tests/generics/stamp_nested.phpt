--TEST--
Generics M2: nested instantiations enforce with exact mangled names
--FILE--
<?php
class Vec<T> { public function push(T $x): void {} }

$vv = new Vec<Vec<int>>();
$vv->push(new Vec<int>());
echo get_class($vv), "\n";
try { $vv->push(new Vec<string>()); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump(new Vec<int>() instanceof Vec<int>);
?>
--EXPECTF--
Vec<Vec<int>>
Vec<Vec<int>>::push(): Argument #1 ($x) must be of type Vec<int>, Vec<string> given, called in %s on line %d
bool(true)
