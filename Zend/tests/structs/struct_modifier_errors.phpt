--TEST--
Structs: modifiers parse through class_modifiers and get targeted diagnostics
--FILE--
<?php

// Only readonly is meaningful on a struct (implicitly final, never abstract),
// but the other modifiers parse and produce specific errors instead of raw
// parse errors.
foreach ([
    'abstract struct A { public int $x = 1; }',
    'final struct F { public int $x = 1; }',
    'readonly readonly struct RR { public int $x = 1; }',
    'abstract readonly struct AR { public int $x = 1; }',
] as $code) {
    try {
        eval($code);
        echo "no error\n";
    } catch (CompileError $e) {
        echo $e->getMessage(), "\n";
    }
}

// readonly struct still works and marks the class and its properties.
readonly struct Span {
    public function __construct(public int $start, public int $length) {}
}
$r = new ReflectionClass(Span::class);
var_dump($r->isReadOnly(), $r->isFinal());
$s = new Span(0, 5);
try {
    $s->start = 1;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECT--
Cannot use the abstract modifier on a struct
Cannot use the final modifier on a struct
Multiple readonly modifiers are not allowed
Cannot use the abstract modifier on a struct
bool(true)
bool(true)
Cannot modify readonly property Span::$start
