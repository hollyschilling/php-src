--TEST--
Named extensions are class-table symbols: class_exists() sees (and autoloads) them; instantiation and inheritance fail
--FILE--
<?php
namespace App;
use extension Vendor\Greeter;

class Widget { public function __construct(public string $name) {} }

$loads = [];
spl_autoload_register(function ($name) use (&$loads) {
    $loads[] = $name;
    if (str_starts_with($name, 'Vendor\\')) {
        require_once __DIR__ . '/autoload_decl.inc';
    }
});

// class_exists() with autoload triggers loading, exactly as for a class.
var_dump(class_exists('Vendor\Greeter'));
var_dump($loads);

// The symbol is real but not instantiable, and final blocks inheritance.
try { new \Vendor\Greeter(); } catch (\Error $e) { echo $e->getMessage(), "\n"; }
var_dump((new \ReflectionClass('Vendor\Greeter'))->getName());
var_dump((new \ReflectionClass('Vendor\Greeter'))->isInstantiable());

// The extension resolves without a further loader call.
var_dump((new Widget('h'))->greet());
var_dump(count($loads));
?>
--EXPECT--
bool(true)
array(1) {
  [0]=>
  string(14) "Vendor\Greeter"
}
Cannot instantiate abstract class Vendor\Greeter
string(14) "Vendor\Greeter"
bool(false)
string(5) "hi, h"
int(1)
