--TEST--
Autoloading: an unloadable import is not an error and is attempted at most once per request
--FILE--
<?php
use extension Vendor\Nowhere;

$calls = 0;
spl_autoload_register(function ($name) use (&$calls) {
    $calls++; // deliberately loads nothing
});

$o = new stdClass;

try { $o->nope(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $o->nope(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { $o->other(); } catch (Error $e) { echo $e->getMessage(), "\n"; }

// One attempt for Vendor\Nowhere, despite three misses.
var_dump($calls);
?>
--EXPECT--
Call to undefined method stdClass::nope()
Call to undefined method stdClass::nope()
Call to undefined method stdClass::other()
int(1)
