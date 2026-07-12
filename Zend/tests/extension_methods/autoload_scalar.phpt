--TEST--
Autoloading: a named scalar extension is autoloaded at first use on the scalar dispatch path
--FILE--
<?php
use extension Vendor\NumKit;

$loads = [];
spl_autoload_register(function ($name) use (&$loads) {
    $loads[] = $name;
    if (str_starts_with($name, 'Vendor\\')) {
        require_once __DIR__ . '/autoload_decl.inc';
    }
});

var_dump((21)->twice());
var_dump($loads);
?>
--EXPECT--
int(42)
array(1) {
  [0]=>
  string(13) "Vendor\NumKit"
}
