--TEST--
Generics M2: each instantiation has independent static members
--FILE--
<?php
class Counter<T> {
    public static int $count = 0;
    public static function bump(): int { return ++static::$count; }
}

Counter<stdClass>::bump();
Counter<stdClass>::bump();
Counter<DateTime>::bump();
var_dump(Counter<stdClass>::$count);
var_dump(Counter<DateTime>::$count);
var_dump(Counter<stdClass>::class);
?>
--EXPECT--
int(2)
int(1)
string(17) "Counter<stdClass>"
