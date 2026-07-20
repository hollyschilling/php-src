--TEST--
internal and module remain usable as identifiers (contextual keywords)
--FILE--
<?php
function internal($x) { return $x + 1; }
function module($x) { return $x * 2; }
const internal = 40;

echo internal(1), " ", module(2), " ", internal, "\n";

class T {
    const internal = 5;
    const module = 6;
    public $internal = 7;
    public function internal() { return 8; }
    public function module() { return 9; }
    public static function internalStatic() { return 10; }
}

$t = new T();
echo T::internal, " ", T::module, " ", $t->internal, " ", $t->internal(), " ", $t->module(), " ", T::internalStatic(), "\n";
echo (internal) ? 'yes' : 'no', "\n";
echo internal . "-concat\n";
$arr = [internal => 'k']; echo array_key_first($arr), "\n";
?>
--EXPECT--
2 4 40
5 6 7 8 9 10
yes
40-concat
40
