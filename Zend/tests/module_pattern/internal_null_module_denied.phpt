--TEST--
internal members are inaccessible from module-less (null module) code
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';

use Acme\Widget;

$w = new Widget();

try {
    $w->step();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    Widget::boot();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    var_dump(Widget::SECRET);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    var_dump($w->token);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    $w->token = 'x';
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    var_dump(Widget::$counter);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECTF--
Call to internal method Acme\Widget::step() from global scope
Call to internal method Acme\Widget::boot() from global scope
Cannot access internal constant Acme\Widget::SECRET
Cannot access internal property Acme\Widget::$token
Cannot access internal property Acme\Widget::$token
Cannot access internal property Acme\Widget::$counter
