--TEST--
Autoloading does not widen visibility: a non-importing file still cannot see an autoloaded named extension
--FILE--
<?php
namespace App;
use extension Vendor\Greeter;

class Widget { public function __construct(public string $name) {} }

$calls = 0;
spl_autoload_register(function ($name) use (&$calls) {
    $calls++;
    if (str_starts_with($name, 'Vendor\\')) {
        require_once __DIR__ . '/autoload_decl.inc';
    }
});

var_dump((new Widget('h'))->greet()); // loads + resolves here

// A separate compilation unit without the import: gated out, and the
// loader is not consulted again (the name is present in the class table).
$helper = eval('return function (\App\Widget $w) {
    try { return $w->greet(); } catch (\Error $e) { return "Error: " . $e->getMessage(); }
};');
var_dump($helper(new Widget('x')));
var_dump($calls);
?>
--EXPECT--
string(5) "hi, h"
string(51) "Error: Call to undefined method App\Widget::greet()"
int(1)
