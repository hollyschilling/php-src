--TEST--
Cross-feature: mutating extension methods for scalar targets are rejected at compile time
--FILE--
<?php
extension int $n {
    public function bump() mutating: int { return $n + 1; }
}
?>
--EXPECTF--
Fatal error: Extension method bump() for scalar target int cannot be mutating in %s on line %d
