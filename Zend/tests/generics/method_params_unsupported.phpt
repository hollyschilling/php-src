--TEST--
Generics: type-parameter lists attach to classes, interfaces and traits only
--FILE--
<?php
declare(strict_types=1);

// Type parameters are a property of a class, interface or trait declaration
// here; functions and methods do not take them, and neither do call sites.
// Every spelling is rejected at parse time rather than parsed and ignored,
// which is what keeps the syntax available for a later proposal to define.
$rejected = [
    'method declaration'        => 'class MD { public function f::<U>(U $x): U { return $x; } }',
    'method declaration, bare'  => 'class MB { public function f<U>(U $x): U { return $x; } }',
    'static method declaration' => 'class MS { public static function f::<U>(U $x): U { return $x; } }',
    'function declaration'      => 'function fd::<U>(U $x): U { return $x; }',
    'method call'               => 'class CC { public function f($x) { return $x; } } (new CC)->f::<int>(1);',
    'static method call'        => 'class CS { public static function f($x) { return $x; } } CS::f::<int>(1);',
];

foreach ($rejected as $label => $code) {
    try {
        eval($code);
        printf("%-26s ACCEPTED\n", $label);
    } catch (ParseError $e) {
        printf("%-26s %s\n", $label, $e->getMessage());
    }
}

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
method declaration         syntax error, unexpected token "::<", expecting "("
method declaration, bare   syntax error, unexpected generic '<' "<", expecting "("
static method declaration  syntax error, unexpected token "::<", expecting "("
function declaration       syntax error, unexpected token "::<", expecting "("
method call                syntax error, unexpected token "::<"
static method call         syntax error, unexpected token "::<"
int(7)
constructor enforced
