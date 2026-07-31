--TEST--
Variance: annotations are interface-only
--FILE--
<?php
class Bad<out T> {}
?>
--EXPECTF--
Fatal error: Variance annotations are only permitted on interface type parameters (parameter T) in %s on line %d
