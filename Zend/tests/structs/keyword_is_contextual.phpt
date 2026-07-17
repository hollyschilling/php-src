--TEST--
Structs: "struct" remains usable as a class, function, constant and member name
--FILE--
<?php

// As a class name.
class struct {
    const FOO = 'class const';
    public $bar = 'class prop';
    public static function make(): static { return new struct(); }
}

$s = new struct();
var_dump(struct::FOO, $s->bar, struct::make() instanceof struct);

// As a function name.
function struct(int $x): int { return $x * 2; }
var_dump(struct(21));

// As a global constant.
const struct = 'global const';
var_dump(struct);

// As method, property and class-constant names (semi_reserved).
class Holder {
    public $struct = 'prop';
    const struct = 'const';
    public function struct(): string { return 'method'; }
}

$h = new Holder();
var_dump($h->struct, Holder::struct, $h->struct());

// In type positions and as a parameter type.
function takes(struct $s): struct { return $s; }
var_dump(takes($s) instanceof struct);

// The lookahead carve-out: "struct" as a class name before extends/implements.
interface IFace {}
class Sub extends struct implements IFace {}
var_dump(new Sub() instanceof struct);

?>
--EXPECT--
string(11) "class const"
string(10) "class prop"
bool(true)
int(42)
string(12) "global const"
string(4) "prop"
string(5) "const"
string(6) "method"
bool(true)
bool(true)
