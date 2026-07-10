--TEST--
Surfaces: inheritance rule violations (each case in a separate process)
--FILE--
<?php
$cases = [
    'redeclare' => 'class P { surface W; } class C extends P { surface W; }',
    'extend-inherited' => 'class P { surface W; } class C extends P { surface[W] function g(){} }',
    'drop-on-override' => 'class P { surface W; surface[W] function f(){} } class C extends P { surface R; surface[R] function f(){} }',
    'undeclared' => 'class A { surface[Nope] function f(){} }',
    'narrow-to-protected' => 'class P { surface W; surface[W] function f(){} } class C extends P { protected function f(){} }',
];
foreach ($cases as $name => $code) {
    $out = shell_exec(PHP_BINARY . ' -d display_errors=1 -r ' . escapeshellarg($code) . ' 2>&1');
    echo $name, ": ", trim((string) $out), "\n\n";
}
?>
--EXPECTF--
redeclare: Fatal error: Cannot redeclare surface W on C (owned by P) in Command line code on line 1
Stack trace:
#0 {main}

extend-inherited: Fatal error: Cannot add member g to surface W inherited from P (a subclass may not extend a surface it does not own) in Command line code on line 1
Stack trace:
#0 {main}

drop-on-override: Fatal error: Override of f in C must keep surface W (an override may widen accessibility but not drop a surface) in Command line code on line 1
Stack trace:
#0 {main}

undeclared: Fatal error: Undeclared surface Nope on member f of class A in Command line code on line 1
Stack trace:
#0 {main}

narrow-to-protected: Fatal error: Access level to C::f() must be public (as in class P) in Command line code on line 1
Stack trace:
#0 {main}
