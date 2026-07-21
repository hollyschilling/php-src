--TEST--
export extension: `use module` activates an exported named extension; direct `use extension` still works; the name stays module-gated
--FILE--
<?php
class Widget { public function __construct(public string $name) {} }
require __DIR__ . '/ee_pack.inc';

// Activation via `use module` alone — the consumer never names the extension.
// (eval so the definition above is registered before this consumer compiles.)
eval('namespace A; use module Acme\Ext\Pack; echo "use module: ", (new \Widget("hi"))->shout(), "\n";');

// A module-member extension is still directly importable by name from outside.
eval('namespace B; use extension Acme\Ext\WidgetHelpers; echo "direct: ", (new \Widget("ok"))->shout(), "\n";');

// Redundant module + direct import: de-duplicated, single effect, no error.
eval('namespace C; use module Acme\Ext\Pack; use extension Acme\Ext\WidgetHelpers; echo "both: ", (new \Widget("yo"))->shout(), "\n";');

// Containment: a bare compile-time reference to the extension name from outside
// the module hits the acquisition gate, exactly as a member class would.
try {
    eval('namespace D; new \Acme\Ext\WidgetHelpers();');
} catch (\Throwable $e) {
    echo "gated: ", $e->getMessage(), "\n";
}

// Observation surface stays ungated, matching member classes.
var_dump(class_exists('Acme\Ext\WidgetHelpers'));
var_dump((new ReflectionClass('Acme\Ext\WidgetHelpers'))->isInstantiable());
?>
--EXPECT--
use module: HI
direct: OK
both: YO
gated: Cannot access class Acme\Ext\WidgetHelpers of module Acme\Ext\Pack from outside the module; import the module with 'use module'
bool(true)
bool(false)
