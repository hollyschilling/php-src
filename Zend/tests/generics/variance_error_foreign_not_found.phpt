--TEST--
Variance: unresolvable foreign template around a variant parameter fails the deep check
--FILE--
<?php
interface Odd<in T> {
    public function f(MissingTemplate<T> $x): void;
}
echo "declared fine\n";
try {
    eval('class I1 implements Odd<stdClass> { public function f(MissingTemplate<stdClass> $x): void {} }');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
/* foreign refs without variant mentions need no verification at all */
interface Fine<in T> {
    public function g(MissingTemplate<stdClass> $x, T $y): void;
}
eval('class I2 implements Fine<stdClass> { public function g(MissingTemplate<stdClass> $x, stdClass $y): void {} }');
echo "no variant mention: ok\n";
?>
--EXPECT--
declared fine
Cannot verify variance of Odd: class MissingTemplate (in parameter type of f) was not found
no variant mention: ok
