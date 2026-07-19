--TEST--
Structs: mutating modifier target and duplicate errors (catchable at compile time)
--FILE--
<?php

$cases = [
    'struct S1 { public mutating int $x = 0; }',
    'struct S2 { public mutating const FOO = 1; }',
    'struct S3 { public function __construct(mutating int $x) {} }',
    'struct S4 { public int $x = 0 { mutating get => 1; } }',
    'struct S5 { public int $x = 0; public mutating mutating function f(): void {} }',
    'trait T6 { public function f(): void {} } struct S6 { use T6 { f as mutating g; } public int $x = 0; }',
];

foreach ($cases as $code) {
    try {
        eval($code);
    } catch (CompileError $e) {
        echo $e->getMessage(), "\n";
    }
}

?>
--EXPECT--
Cannot use the mutating modifier on a property
Cannot use the mutating modifier on a class constant
Cannot use the mutating modifier on a parameter
Cannot use the mutating modifier on a property hook
Multiple mutating modifiers are not allowed
Cannot use "mutating" as method modifier in trait alias
