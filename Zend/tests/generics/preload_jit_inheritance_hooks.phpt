--TEST--
Preloaded generic tracing JIT preserves inherited methods and property hook type bindings
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.protect_memory=1
opcache.jit=tracing
opcache.jit_buffer_size=16M
opcache.jit_hot_loop=2
opcache.jit_hot_func=2
opcache.preload={PWD}/preload_jit_inheritance_defs.inc
--SKIPIF--
<?php
if (PHP_OS_FAMILY === 'Windows') die('skip Preloading is not supported on Windows');
if (!isset(opcache_get_configuration()['directives']['opcache.jit'])) die('skip JIT is not available');
?>
--FILE--
<?php
declare(strict_types=1);
$child = new PreloadJitChild<float>();
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($child->keep(1.5, 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
$concrete = new PreloadJitConcrete();
var_dump($concrete->keep(17, 300));
try { $concrete->keep('wrong', 300); } catch (TypeError $e) { echo "inherited type enforced\n"; }
$ints = new PreloadJitHooks<int>(18);
$strings = new PreloadJitHooks<string>('hook');
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($ints->value);
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($strings->value);
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
var_dump($ints->value);
try { $ints->value = 'wrong'; } catch (TypeError $e) { echo "hook type enforced\n"; }
?>
--EXPECT--
float(1.5)
bool(true)
int(17)
inherited type enforced
int(18)
bool(true)
string(4) "hook"
bool(true)
int(18)
hook type enforced
