--TEST--
Plain '<' after 'new NAME' / 'instanceof NAME' is committed to generic arguments
--FILE--
<?php
class Vec<T> { public function __construct() {} }
class Map<K, V> { public function __construct() {} }
class Plain {}

// commas are fine after new/instanceof: no heuristic, the grammar commits
$m = new Map<string, int>();
var_dump($m::class);
var_dump($m instanceof Map<string, int>);
var_dump($m instanceof Map<int, int>);

// no-parens new
$v = new Vec<int>;
var_dump($v::class);

// The sacrificed reading is a loud parse error now, not a silent comparison:
foreach (['$r = new Plain < 5;', '$r = new Plain() instanceof Plain < 5;'] as $src) {
    try {
        eval($src);
        echo "no error\n";
    } catch (ParseError $e) {
        echo "ParseError\n";
    }
}
?>
--EXPECT--
string(15) "Map<string,int>"
bool(true)
bool(false)
string(8) "Vec<int>"
ParseError
ParseError
