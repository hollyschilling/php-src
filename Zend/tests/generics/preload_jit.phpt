--TEST--
Preloaded generic classes and method templates can trace without sharing specialized code
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
opcache.preload={PWD}/preload_jit_defs.inc
--SKIPIF--
<?php
if (PHP_OS_FAMILY === 'Windows') die('skip Preloading is not supported on Windows');
if (!isset(opcache_get_configuration()['directives']['opcache.jit'])) die('skip JIT is not available');
?>
--FILE--
<?php
declare(strict_types=1);
$ints = new PreloadJitBox<int>();
$strings = new PreloadJitBox<string>();
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($ints->keep(42, 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($strings->keep('string', 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
var_dump($ints->keep(7, 300));
try { $strings->keep(123, 300); } catch (TypeError $e) { echo "class type enforced\n"; }

$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($ints->pick<int>(11, 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
var_dump($ints->pick<string>('method', 300));
var_dump($ints->pick<int>(12, 300));
try { $ints->pick<int>('wrong', 300); } catch (TypeError $e) { echo "method type enforced\n"; }

$methods = new PreloadJitMethods();
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($methods->keep<int>(23, 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
var_dump($methods->keep<string>('plain class', 300));
var_dump($methods->keep<int>(24, 300));
?>
--EXPECT--
int(42)
bool(true)
string(6) "string"
bool(true)
int(7)
class type enforced
int(11)
bool(true)
string(6) "method"
int(12)
method type enforced
int(23)
bool(true)
string(11) "plain class"
int(24)
