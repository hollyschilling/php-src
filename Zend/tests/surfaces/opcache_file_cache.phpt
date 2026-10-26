--TEST--
Surfaces: metadata and grants survive opcache file_cache round-trip
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_cache_only=1
opcache.file_cache={TMP}
--FILE--
<?php
require __DIR__ . '/limiter.inc';

$r = new RateLimiter();
try { $r->setClock('x'); } catch (Error $e) { echo "denied\n"; }
require __DIR__ . '/limiter_consumer.inc';
consumerWithGrant($r);
var_dump($r->attempt('k'));
?>
--EXPECT--
denied
granted
bool(false)
