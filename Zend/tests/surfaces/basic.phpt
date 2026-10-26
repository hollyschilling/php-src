--TEST--
Surfaces: declaration, member gating, file-level grant
--FILE--
<?php
require __DIR__ . '/limiter.inc';

$r = new RateLimiter();
var_dump($r->attempt('k'));
try {
    $r->setClock('frozen');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
require __DIR__ . '/limiter_consumer.inc';
consumerWithGrant($r);
var_dump($r->attempt('k'));
?>
--EXPECT--
bool(true)
Call to surface method RateLimiter::setClock() from global scope (grant it with "use RateLimiter with surface[...]")
granted
bool(false)
