--TEST--
internal enforcement applies to dynamic access spellings (protected parity); Reflection pierces
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';

$widgetClass = 'Acme\Widget';
$w = new $widgetClass();

$m = 'step';
try {
    $w->$m();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    call_user_func([$w, 'step']);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
try {
    call_user_func('Acme\Widget::boot');
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
var_dump(is_callable([$w, 'step']));

// Reflection pierces internal, in parity with private/protected
$r = new ReflectionMethod($w, 'step');
var_dump($r->invoke($w));
$p = new ReflectionProperty($w, 'token');
var_dump($p->getValue($w));
?>
--EXPECTF--
Call to internal method Acme\Widget::step() from global scope
call_user_func(): Argument #1 ($callback) must be a valid callback, cannot access internal method Acme\Widget::step()
call_user_func(): Argument #1 ($callback) must be a valid callback, cannot access internal method Acme\Widget::boot()
bool(false)
string(7) "stepped"
string(3) "tok"
