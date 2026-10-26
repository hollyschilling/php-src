--TEST--
Acquisition gate on inheritance: bare cross-module extends/implements/use fail; :> works
--FILE--
<?php
require __DIR__ . '/mp2_kernel_def.inc';
require __DIR__ . '/mp2_kernel_members.inc';
require __DIR__ . '/mp2_kernel_routing.inc';
require __DIR__ . '/mp2_kernel_legacy.inc';

try {
    eval('class BareRouter extends Acme\Http\Routing\Router {}');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

eval('use module Acme\Http\Kernel; class TracingRouter extends Kernel:>Router { public function trace(): string { return static::VERSION . "-traced"; } }');
var_dump((new TracingRouter())->trace());

// Same-module inheritance by bare name works (instantiate dynamically:
// FastRouter itself is a member and not exported)
eval('namespace Acme\Http\Routing; module Acme\Http\Kernel; class FastRouter extends Router {}');
$fastClass = 'Acme\Http\Routing\FastRouter';
var_dump(get_parent_class(new $fastClass()));
?>
--EXPECT--
Cannot extend class Acme\Http\Routing\Router of module Acme\Http\Kernel from outside the module; import the module with 'use module'
string(9) "v1-traced"
string(24) "Acme\Http\Routing\Router"
