--TEST--
Generics: using one generic trait at two argument sets collides like any two traits
--FILE--
<?php
trait Cache<T> {
    public function remember(T $value): T { return $value; }
    public function version(): int { return 1; } // no T: identical across instantiations
}

class Adapted {
    use Cache<int>, Cache<string> {
        Cache<int>::remember insteadof Cache<string>;
        Cache<string>::remember as rememberString;
    }
}
$a = new Adapted();
var_dump($a->remember(42));
var_dump($a->rememberString("x"));
var_dump($a->version());
try { $a->rememberString([]); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

class Bare { use Cache<int>, Cache<string>; }
?>
--EXPECTF--
int(42)
string(1) "x"
int(1)
Adapted::rememberString(): Argument #1 ($value) must be of type string, array given, called in %s on line %d

Fatal error: Trait method Cache<string>::remember has not been applied as Bare::remember, because of collision with Cache<int>::remember in %s on line %d
