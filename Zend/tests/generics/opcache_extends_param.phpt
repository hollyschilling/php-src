--TEST--
Generics: deferred-parent templates persist through opcache (SHM) and stamp from the cached template
--INI--
opcache.enable=1
opcache.enable_cli=1
--FILE--
<?php
$defs = __DIR__ . '/extends_param_defs.inc';
require $defs;

$v = new OpcMyVec<int>([1, 2]);
$v->push(3);
var_dump($v->total());          // parent:: through the graft
var_dump($v instanceof OpcVec<int>);
try {
    $v->push("bad");
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(6)
bool(true)
OpcVec<int>::push(): Argument #1 ($v) must be of type int, string given, called in %s on line %d
