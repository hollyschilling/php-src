--TEST--
Variance: variant parameter in an invariant foreign slot errors at first instantiation
--FILE--
<?php
class Box<T> { public function __construct() {} }
interface Bad<out T> {
    public function wrap(): Box<T>;
}
class Foo {}
echo "declared fine\n";
try {
    eval('class Impl implements Bad<Foo> { public function wrap(): Box<Foo> { return new Box<Foo>(); } }');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
declared fine
Covariant type parameter T of Bad may not appear in an invariant position (return type of wrap)
