--TEST--
Generics: param-dependent extends — signature compatibility is checked per instantiation with substituted types
--FILE--
<?php

class WB<T> { public function add(T $v): void {} }
class WC<T> extends WB<T> { public function add(int $v): void {} }

new WC<int>();      // add(int) overriding add(int): compatible
echo "WC<int> ok\n";

new WC<string>();   // add(int) overriding add(string): incompatible
echo "unreachable\n";
?>
--EXPECTF--
WC<int> ok

Fatal error: Declaration of WC<string>::add(int $v): void must be compatible with WB<string>::add(string $v): void in %s on line %d
