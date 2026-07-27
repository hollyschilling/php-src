--TEST--
Module definition: duplicate definition, non-export statement, definition-less module
--FILE--
<?php
// Duplicate definition (runtime, catchable)
eval('namespace A; module Dup { }');
try {
    eval('namespace A; module Dup { }');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

// Non-export statement inside the block (parser, catchable CompileError)
try {
    eval('namespace A; module Three { frobnicate A\X; }');
} catch (CompileError $e) {
    echo $e->getMessage(), "\n";
}

// Definition-less modules are fine: membership without a definition
eval('namespace A; module A\NoDef; class Hidden { internal function f(): string { return "hidden"; } public function g(): string { return $this->f(); } }');
$c = 'A\Hidden';
var_dump((new $c())->g());
?>
--EXPECT--
Module A\Dup is already defined
Unexpected statement in module definition block, expecting 'export'
string(6) "hidden"
