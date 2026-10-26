--TEST--
use extension works from preloaded functions (imports live in the op_array)
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.preload={PWD}/preload_imports.inc
--SKIPIF--
<?php
if (!extension_loaded('Zend OPcache')) die('skip opcache required');
if (substr(PHP_OS, 0, 3) === 'WIN') die('skip opcache.preload not supported on Windows');
?>
--FILE--
<?php
/* Declare the target class and extension at request time... */
require __DIR__ . '/ext_decl.inc';

/* ...and call through the preloaded consumer: its file imported VecUtils,
 * and its op_array (preserved by preloading, never re-executed per
 * request) carries the import. */
var_dump(preloaded_sum(new Vec([4, 5, 6])));

/* This file did not import VecUtils: gating still applies. */
try {
    (new Vec([1]))->sum();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
int(15)
Call to undefined method Vec::sum()
