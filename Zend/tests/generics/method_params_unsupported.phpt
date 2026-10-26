--TEST--
Generics: type-parameter lists attach to classes, interfaces, traits and methods; not functions
--FILE--
<?php
declare(strict_types=1);

// On this branch methods take type-parameter lists (both spellings); free
// functions still do not, and every function spelling is rejected at parse
// time rather than parsed and ignored, which is what keeps that syntax
// available for a later proposal to define.
$rejected = [
    'function declaration'       => 'function fd::<U>(U $x): U { return $x; }',
    'function declaration, bare' => 'function fb<U>(U $x): U { return $x; }',
];

foreach ($rejected as $label => $code) {
    try {
        eval($code);
        printf("%-27s ACCEPTED\n", $label);
    } catch (ParseError $e) {
        printf("%-27s %s\n", $label, $e->getMessage());
    }
}

// Method declarations are accepted in every spelling, and enforced.
class MD { public function f::<U>(U $x): U { return $x; } }
class MB { public function f<U>(U $x): U { return $x; } }
class MS { public static function f::<U>(U $x): U { return $x; } }

var_dump((new MD)->f::<int>(1));
var_dump((new MB)->f::<string>("s"));
var_dump(MS::f::<int>(3));
try { (new MD)->f::<int>("no"); } catch (TypeError $e) { echo "enforced\n"; }

// Type arguments on a method that declares no type parameters stay an error,
// reported at the call rather than silently ignored.
class CC { public function f($x) { return $x; } }
class CS { public static function f($x) { return $x; } }
try { (new CC)->f::<int>(1); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { CS::f::<int>(1); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// A class may of course still declare type parameters and use them in the
// signature of an ordinary method.
class Box<T> {
    public function __construct(private T $value) {}
    public function get(): T { return $this->value; }
}

$box = new Box<int>(7);
var_dump($box->get());
try { new Box<int>("no"); } catch (TypeError $e) { echo "constructor enforced\n"; }
?>
--EXPECT--
function declaration        syntax error, unexpected token "::<", expecting "("
function declaration, bare  syntax error, unexpected generic '<' "<", expecting "("
int(1)
string(1) "s"
int(3)
enforced
Method CC::f() is not generic
Method CS::f() is not generic
int(7)
constructor enforced
