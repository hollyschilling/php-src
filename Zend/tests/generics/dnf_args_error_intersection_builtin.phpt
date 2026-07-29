--TEST--
Composite (DNF) arguments: builtins cannot be intersection members
--FILE--
<?php
interface B {}
class Vec<T> {}
$x = new Vec<int&B>();
?>
--EXPECTF--
Fatal error: Type int cannot be part of an intersection type in %s on line %d
