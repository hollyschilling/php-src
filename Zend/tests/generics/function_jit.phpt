--TEST--
Function JIT compiles generic specializations without sharing type-specific machine code
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.jit=function
opcache.jit_buffer_size=16M
opcache.protect_memory=1
--SKIPIF--
<?php
if (!isset(opcache_get_configuration()['directives']['opcache.jit'])) die('skip JIT is not available');
?>
--FILE--
<?php
declare(strict_types=1);
require __DIR__ . '/preload_jit_defs.inc';
$ints = new PreloadJitBox<int>();
$strings = new PreloadJitBox<string>();
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($ints->keep(42, 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($strings->keep('value', 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
var_dump($ints->keep(43, 300));
try { $ints->keep('wrong', 300); } catch (TypeError $e) { echo "class type enforced\n"; }
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($ints->pick<int>(44, 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
var_dump($ints->pick<string>('method', 300));
var_dump($ints->pick<int>(45, 300));
try { $ints->pick<int>('wrong', 300); } catch (TypeError $e) { echo "method type enforced\n"; }
$methods = new PreloadJitMethods();
$before = opcache_get_status(false)['jit']['buffer_free'];
var_dump($methods->keep<int>(46, 300));
var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
var_dump($methods->keep<string>('plain class', 300));
var_dump($methods->keep<int>(47, 300));
?>
--EXPECT--
int(42)
bool(true)
string(5) "value"
bool(true)
int(43)
class type enforced
int(44)
bool(true)
string(6) "method"
int(45)
method type enforced
int(46)
bool(true)
string(11) "plain class"
int(47)
