--TEST--
internal members are inaccessible from classes in a different module
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';
require __DIR__ . '/module_spy.inc';

$widgetClass = 'Acme\Widget';
$w = new $widgetClass();

try {
    call_user_func('Vendor\Spy::snoop', $w);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    call_user_func('Vendor\Spy::snoopConst');
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    call_user_func('Vendor\Spy::snoopProp', $w);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Call to internal method Acme\Widget::step() from scope Vendor\Spy
Cannot access internal constant Acme\Widget::SECRET
Cannot access internal property Acme\Widget::$token
