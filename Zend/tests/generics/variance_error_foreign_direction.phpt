--TEST--
Variance: composed polarity through a foreign template still rejects wrong directions
--FILE--
<?php
interface ReadableSequence<out T> { public function get(int $i): T; }
class Foo {}
/* in T inside an out slot at output position composes to output: illegal */
interface Bad<in T> {
    public function all(): ReadableSequence<T>;
}
echo "declared fine\n";
try {
    eval('class I1 implements Bad<Foo> { public function all(): ReadableSequence<Foo> { throw new Exception(); } }');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
/* extended interface slot: output polarity composed through out keeps output */
interface BadExt<in T> extends ReadableSequence<T> {
    public function extra(T $x): void;
}
try {
    eval('class I2 implements BadExt<Foo> {
        public function get(int $i): Foo { return new Foo(); }
        public function extra(Foo $x): void {}
    }');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
declared fine
Contravariant type parameter T of Bad may not appear in an output position (return type of all)
Contravariant type parameter T of BadExt may not appear in an output position (extended interface of ReadableSequence<T>)
