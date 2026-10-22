--TEST--
Autoloading: an exception thrown by the autoloader propagates instead of being replaced by the undefined-method error
--FILE--
<?php
use extension Vendor\Missing;

spl_autoload_register(function ($name) {
    throw new RuntimeException("loader says no: $name");
});

$o = new stdClass;
try {
    $o->nope();
} catch (RuntimeException $e) {
    echo $e->getMessage(), "\n";
}

// Negative-cached now: the loader is not consulted again, so the ordinary
// errors come back — on the object path and on the scalar path.
try { $o->nope(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
try { "s"->nope(); } catch (Error $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
loader says no: Vendor\Missing
Call to undefined method stdClass::nope()
Call to a member function nope() on string
