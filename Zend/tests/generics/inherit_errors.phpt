--TEST--
Generics M3: bare templates cannot be extended or implemented
--FILE--
<?php
interface Collection<T> { public function add(T $item): void; }
class Vec<T> {}

try { eval("class Bad1 implements Collection {}"); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { eval("class Bad2 extends Vec {}"); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
Bad1 cannot implement generic interface Collection without type arguments
Class Bad2 cannot extend generic class Vec without type arguments
