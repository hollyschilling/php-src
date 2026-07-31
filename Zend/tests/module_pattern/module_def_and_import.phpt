--TEST--
Module definition, use module import, :> resolution, and the acquisition gate
--FILE--
<?php
require __DIR__ . '/mp2_kernel_def.inc';
require __DIR__ . '/mp2_kernel_members.inc';
require __DIR__ . '/mp2_kernel_routing.inc';
require __DIR__ . '/mp2_kernel_legacy.inc';
require __DIR__ . '/mp2_consumer.inc';

$app = new App\Application();

// The whole exported surface works through :>
var_dump($app->handle());

// ::class yields the canonical FQCN
var_dump($app->names());

// instanceof agrees across spellings
var_dump($app->checkInstance());

// Aliased export
var_dump($app->legacyNote());

// Static access through :>
var_dump($app->staticSurface());

// Non-exported member does not resolve
try {
    $app->reachInternalClass();
} catch (Error $e) {
    echo get_class($e), ": ", $e->getMessage(), "\n";
}

// Bare FQCN from outside is a module mismatch, even for exported classes
try {
    new Acme\Http\Routing\Router();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

// Dynamic (string) instantiation is not gated
$name = 'Acme\Http\Routing\Router';
var_dump(get_class(new $name()));

// class_exists is not gated
var_dump(class_exists('Acme\Http\Routing\RouteCompiler'));
?>
--EXPECTF--
int(200)
array(2) {
  [0]=>
  string(17) "Acme\Http\Request"
  [1]=>
  string(24) "Acme\Http\Routing\Router"
}
bool(true)
string(6) "legacy"
array(2) {
  [0]=>
  string(2) "v1"
  [1]=>
  int(%d)
}
Error: %s
Cannot access class Acme\Http\Routing\Router of module Acme\Http\Kernel from outside the module; import the module with 'use module'
string(24) "Acme\Http\Routing\Router"
bool(true)
