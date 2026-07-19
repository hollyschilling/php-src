--TEST--
Structs: the mutating marker only parses between a method's parameter list and return type
--FILE--
<?php

$cases = [
    // Wrong identifier in the marker position.
    'struct S1 { public int $x = 0; public function f() mutatng: void {} }',
    // The marker is not a modifier.
    'struct S2 { public int $x = 0; public mutating function f(): void {} }',
    // Only once.
    'struct S3 { public int $x = 0; public function f() mutating mutating: void {} }',
    // Plain functions and closures have no receiver to mark.
    'function f() mutating: void {}',
    '$g = function () mutating: void {};',
];

foreach ($cases as $code) {
    try {
        eval($code);
    } catch (CompileError $e) {
        echo get_class($e), ": ", $e->getMessage(), "\n";
    }
}

?>
--EXPECT--
CompileError: Unexpected identifier "mutatng" in method signature, expecting "mutating"
ParseError: syntax error, unexpected token "function", expecting variable
ParseError: syntax error, unexpected identifier "mutating", expecting ";" or "{"
ParseError: syntax error, unexpected identifier "mutating", expecting "{"
ParseError: syntax error, unexpected identifier "mutating", expecting "{"
