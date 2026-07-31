--TEST--
Generics M1: instantiating a generic template without type arguments throws
--FILE--
<?php
class Box<T> {
    public function set(T $v): void {}
}
try {
    $b = new Box;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    $b = new Box();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Cannot instantiate generic class Box without type arguments
Cannot instantiate generic class Box without type arguments
