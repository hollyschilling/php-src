--TEST--
export extension: an `as` alias is a compile-time error (extensions are activated, not named)
--FILE--
<?php
try {
    eval('namespace Acme\Ext; module Pack { export extension Acme\Ext\WidgetHelpers as WH; }');
} catch (\CompileError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Exported extensions cannot be aliased; an extension is activated, not named
