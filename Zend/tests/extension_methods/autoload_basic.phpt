--TEST--
Autoloading: an imported-but-unloaded named extension is resolved through the class autoloader at first use
--FILE--
<?php
namespace App;
use extension Vendor\Greeter;
use extension Vendor\NumKit;

class Widget { public function __construct(public string $name) {} }

$loads = [];
spl_autoload_register(function ($name) use (&$loads) {
    $loads[] = $name; // receives the original-case name from the import
    if (str_starts_with($name, 'Vendor\\')) {
        require_once __DIR__ . '/autoload_decl.inc';
    }
});

$w = new Widget('holly');

// Importing is not loading: nothing has been autoloaded yet.
var_dump($loads);

// First use triggers the autoloader; the declaring file registers both
// extensions, so the loader runs exactly once.
var_dump($w->greet());
var_dump((21)->twice());
var_dump($loads);
?>
--EXPECT--
array(0) {
}
string(9) "hi, holly"
int(42)
array(1) {
  [0]=>
  string(14) "Vendor\Greeter"
}
