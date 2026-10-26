--TEST--
Scalar extension methods cannot be partially applied (the receiver is by value)
--FILE--
<?php
extension string $s {
    function pad(int $n, string $c = " "): string { return str_pad($s, $n, $c); }
}

try { $f = "abc"->pad(?); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $f = "abc"->pad(5, ...); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $f = "abc"->pad(?, c: "-"); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $f = "abc"->pad(...); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// Plain calls still work afterwards.
var_dump("abc"->pad(5, "-"));
?>
--EXPECT--
Cannot create a partial application from a scalar extension method
Cannot create a partial application from a scalar extension method
Cannot create a partial application from a scalar extension method
Cannot create a first-class callable from a scalar extension method
string(5) "abc--"
