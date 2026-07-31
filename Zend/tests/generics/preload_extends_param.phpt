--TEST--
Generics: preload sweep stamps deferred-parent instantiations (and their grafted parents) into shared memory
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/preload_extends_param.inc
--SKIPIF--
<?php
if (PHP_OS_FAMILY === 'Windows') die('skip Preload is not supported on Windows');
?>
--FILE--
<?php
// Both the instantiation and its grafted parent were stamped at preload time.
var_dump(in_array('PreMyVec<int>', get_declared_classes()));
var_dump(in_array('PreVec<int>', get_declared_classes()));

$v = new PreMyVec<int>();
$v->push(2);
$v->push(3);
var_dump($v->double());
var_dump($v instanceof PreVec<int>);
?>
--EXPECT--
bool(true)
bool(true)
int(4)
bool(true)
