--TEST--
Generics M2: dynamic mangled names (DI-container backdoor), class_exists, error cases
--FILE--
<?php
class Vec<T> { public function push(T $x): void {} }
class NotGeneric {}

$cls = "Vec<DateTime>";
$d = new $cls;
echo get_class($d), "\n";
var_dump(class_exists("Vec<ArrayObject>"));
try { new Vec<int,string>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { new NotGeneric<int>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $bad = "Vec<not valid>"; new $bad; } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Unbounded type arguments stay lazy: the stamp succeeds even for a class
// that does not exist -- enforcement fails naturally at first use.
$lazy = new Vec<MissingClass>();
echo get_class($lazy), "\n";
try { $lazy->push(new stdClass); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
Vec<DateTime>
bool(true)
Generic class Vec expects 1 type argument, 2 given
Class NotGeneric is not generic
Malformed generic class name "Vec<not valid>"
Vec<MissingClass>
Vec<MissingClass>::push(): Argument #1 ($x) must be of type MissingClass, stdClass given, called in %s on line %d
