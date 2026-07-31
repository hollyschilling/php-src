--TEST--
Generic methods: type parameters are not constants (T::class in constant expressions)
--FILE--
<?php
class Bad<T> {
    const X = [T::class];
}
?>
--EXPECTF--
Fatal error: Cannot use type parameter T::class in a constant expression in %s on line %d
