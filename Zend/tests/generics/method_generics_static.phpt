--TEST--
Generic methods: static call forms (Foo::, self::, parent::, static::) and visibility
--FILE--
<?php
declare(strict_types=1);

class Price { public function __construct(public mixed $v = null) {} }
class Order {}

class Seq<T> {
    public function __construct(private array $items = []) {}
    public static function of<U>(mixed ...$vals): Seq<U> {
        return new Seq<U>($vals);
    }
    public function count(): int { return count($this->items); }
    public function viaSelf(): object { return self::of<Price>(new Price()); }
    public function viaStatic(): object { return static::of<Order>(new Order()); }
}
class SubSeq<T> extends Seq<T> {
    public function viaParent(): object { return parent::of<Price>(new Price()); }
}

$s = Seq<int>::of<Price>(new Price(1), new Price(2));
var_dump(get_class($s), $s->count());
$q = new Seq<int>();
var_dump(get_class($q->viaSelf()), get_class($q->viaStatic()));
var_dump(get_class((new SubSeq<int>())->viaParent()));

// Visibility: private generic methods, instance and static.
class V<T> {
    private function priv<U>(): string { return "p"; }
    public function ok(): string { return $this->priv<Price>(); }
    private static function sPriv<U>(): string { return "sp"; }
    public static function sOk(): string { return self::sPriv<Price>(); }
}
$v = new V<int>();
var_dump($v->ok(), V<int>::sOk());
try { $v->priv<Price>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { V<int>::sPriv<Price>(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
string(10) "Seq<Price>"
int(2)
string(10) "Seq<Price>"
string(10) "Seq<Order>"
string(10) "Seq<Price>"
string(1) "p"
string(2) "sp"
Call to private method V<int>::priv<Price>() from global scope
Call to private method V<int>::sPriv<Price>() from global scope
