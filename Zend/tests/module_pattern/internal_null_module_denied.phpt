--TEST--
internal members are inaccessible from module-less (null module) code
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';

$widgetClass = 'Acme\Widget';
$w = new $widgetClass();

try {
    $w->step();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    call_user_func('Acme\Widget::boot');
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
try {
    var_dump(constant('Acme\Widget::SECRET'));
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

// Through the export surface, class access works but internal members
// stay internal.
eval('use module Acme\Kernel;
class NullConsumer {
    public function probe(): array {
        $errors = [];
        $w = new Kernel:>Widget();
        try { $w->step(); } catch (Error $e) { $errors[] = $e->getMessage(); }
        try { Kernel:>Widget::boot(); } catch (Error $e) { $errors[] = $e->getMessage(); }
        try { $x = Kernel:>Widget::SECRET; } catch (Error $e) { $errors[] = $e->getMessage(); }
        try { $x = Kernel:>Widget::$counter; } catch (Error $e) { $errors[] = $e->getMessage(); }
        return $errors;
    }
}');
var_dump((new NullConsumer)->probe());
?>
--EXPECTF--
Call to internal method Acme\Widget::step() from global scope
call_user_func(): Argument #1 ($callback) must be a valid callback, cannot access internal method Acme\Widget::boot()
Cannot access internal constant Acme\Widget::SECRET
Cannot access internal property Acme\Widget::$token
Cannot access internal property Acme\Widget::$token
array(4) {
  [0]=>
  string(%d) "Call to internal method Acme\Widget::step() from scope NullConsumer"
  [1]=>
  string(%d) "Call to internal method Acme\Widget::boot() from scope NullConsumer"
  [2]=>
  string(%d) "Cannot access internal constant Acme\Widget::SECRET"
  [3]=>
  string(%d) "Cannot access internal property Acme\Widget::$counter"
}
