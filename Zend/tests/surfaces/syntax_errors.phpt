--TEST--
Surfaces: compile-time rejections
--FILE--
<?php
$cases = [
    'private-surface' => 'class A { surface T; private surface[T] function f(){} }',
    'public-surface' => 'class A { surface T; public surface[T] function f(){} }',
    'static-surface' => 'class A { surface T; surface[T] static function f(){} }',
    'iface-decl' => 'interface I { surface T; }',
    'trait-decl' => 'trait T1 { surface T; }',
    'trait-member' => 'trait T1 { surface[X] function f(){} }',
    'magic' => 'class A { surface T; surface[T] function __get($n){} }',
    'dup-decl' => 'class A { surface T; surface T; }',
    'dup-name' => 'class A { surface T; surface[T, T] function f(){} }',
    'promoted' => 'class A { surface T; function __construct(surface[T] int $x) {} }',
    'use-function' => 'use function strlen with surface[T];',
    'in-body-plain-use' => 'function f() { use ArrayObject; }',
    'in-body-alias' => 'function f() { use ArrayObject as AO with surface[T]; }',
    'use-extension-grant' => 'use extension Foo with surface[T];',
];
foreach ($cases as $name => $code) {
    $out = shell_exec(PHP_BINARY . ' -d display_errors=1 -r ' . escapeshellarg($code) . ' 2>&1');
    echo $name, ": ", trim((string) $out), "\n\n";
}
?>
--EXPECTF--
private-surface: Fatal error: Cannot combine the surface modifier with public, protected, or private in Command line code on line 1

public-surface: Fatal error: Cannot combine the surface modifier with public, protected, or private in Command line code on line 1

static-surface: Fatal error: Cannot use the surface modifier on a static member (not yet supported) in Command line code on line 1

iface-decl: Fatal error: Interface I cannot declare surfaces in Command line code on line 1
Stack trace:
#0 {main}

trait-decl: Fatal error: Trait T1 cannot declare surfaces (not yet supported) in Command line code on line 1
Stack trace:
#0 {main}

trait-member: Fatal error: Trait T1 cannot declare surface members (not yet supported) in Command line code on line 1
Stack trace:
#0 {main}

magic: Fatal error: Magic method A::__get() cannot be a surface member in Command line code on line 1
Stack trace:
#0 {main}

dup-decl: Fatal error: Cannot redeclare surface T on A in Command line code on line 1
Stack trace:
#0 {main}

dup-name: Fatal error: Duplicate surface name T in surface[...] modifier in Command line code on line 1
Stack trace:
#0 {main}

promoted: Fatal error: Cannot use the surface modifier on a parameter in Command line code on line 1

use-function: Fatal error: Cannot grant surfaces on a function import (only classes have surfaces) in Command line code on line 1
Stack trace:
#0 {main}

in-body-plain-use: Fatal error: A use statement inside a function may only grant surfaces (use ClassName with surface[...]) in Command line code on line 1
Stack trace:
#0 {main}

in-body-alias: Fatal error: Cannot alias in a surface grant inside a function (grants do not import) in Command line code on line 1
Stack trace:
#0 {main}

use-extension-grant: Fatal error: Cannot grant surfaces on an extension import (extensions have no surfaces) in Command line code on line 1
Stack trace:
#0 {main}
