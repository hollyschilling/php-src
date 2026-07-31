--TEST--
module_loader_register(): definitions load lazily at compile time of the importer
--FILE--
<?php
module_loader_register(function (string $fqmn) {
    echo "loading $fqmn\n";
    require __DIR__ . '/mp2_kernel_def.inc';
    require __DIR__ . '/mp2_kernel_members.inc';
});

eval('use module Acme\Http\Kernel; class App { public function path(): string { return (new Kernel:>Request())->path; } }');
var_dump((new App)->path());
?>
--EXPECT--
loading Acme\Http\Kernel
string(1) "/"
