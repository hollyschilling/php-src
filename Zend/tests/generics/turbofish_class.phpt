--TEST--
Turbofish '::<' is accepted at class-level expression-site argument lists
--FILE--
<?php
class Vec<T> {
    public function __construct(public mixed $seed = null) {}
    public static function make(mixed $s): static { return new static($s); }
    public const WHO = 'vec';
}
class Pair<A, B> {
    public static function mk(): static { return new static(); }
}
class Name {} class Length {}

// new + turbofish
$a = new Vec::<int>(1);
var_dump($a::class);

// static access: static method, const, ::class
var_dump(Pair::<Name, Length>::mk()::class);
var_dump(Vec::<string>::WHO);
var_dump(Vec::<string>::class);

// instanceof
var_dump($a instanceof Vec::<int>);

// nested instantiation + fused '>>' close inside a turbofish list
$b = new Vec::<Vec<int>>(2);
var_dump($b::class);
?>
--EXPECT--
string(8) "Vec<int>"
string(17) "Pair<Name,Length>"
string(3) "vec"
string(11) "Vec<string>"
bool(true)
string(13) "Vec<Vec<int>>"
