--TEST--
Generics: a preloaded instantiation keeps its substituted signature intact
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.protect_memory=1
opcache.preload={PWD}/preload_arg_info_lifetime.inc
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
// Reading the parameter name back verbatim, rather than as garbage or a
// crash, is what proves the substituted signature survived preloading.
$holder = new Holder<Measured>();
$holder->store(new Measured());
var_dump(get_class($holder->fetch()));

try {
    $holder->store(42);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

$reflected = new ReflectionMethod('Holder<Measured>', 'store');
var_dump($reflected->getParameters()[0]->getName());
var_dump((string) $reflected->getParameters()[0]->getType());
?>
--EXPECTF--
string(8) "Measured"
Holder<Measured>::store(): Argument #1 ($element) must be of type Measured, int given, called in %s on line %d
string(7) "element"
string(8) "Measured"
