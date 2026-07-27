--TEST--
Generic methods: call-site type arguments must be concrete (params rejected even nested)
--FILE--
<?php
class Box<T> {}
class Seq<T> { public function map<U>(): void {} }
class C<T> {
    public function go(Seq<T> $s): void {
        $s->map<Box<T>>();
    }
}
?>
--EXPECTF--
Fatal error: Cannot use type parameter T as a generic type argument (type arguments must be concrete in this version) in %s on line %d
