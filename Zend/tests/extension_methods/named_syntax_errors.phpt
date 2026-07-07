--TEST--
Named extension syntax errors: wrong contextual keyword, aliased import
--FILE--
<?php
try {
    eval('extension Foo bar Baz $b {}');
} catch (CompileError $e) {
    echo $e->getMessage(), "\n";
}
/* Aliasing an extension import is a fatal compile error, consistent with
 * other invalid `use` forms. Must come last: it terminates the script. */
eval('use extension Foo as Bar;');
?>
--EXPECTF--
Unexpected identifier "bar", expected "on" in extension declaration

Fatal error: Cannot alias extension Foo: extension imports do not support "as" in %s(%d) : eval()'d code on line %d
