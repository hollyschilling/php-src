--TEST--
Scalar targets inside a namespace are still the built-in types, not namespaced names
--FILE--
<?php
namespace App;

extension string $s {
    public function first(): string { return $s[0]; }
}
var_dump("xyz"->first());
?>
--EXPECT--
string(1) "x"
