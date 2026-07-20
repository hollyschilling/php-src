--TEST--
Generics P3: preload stamps the closed world of instantiations into SHM
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.protect_memory=1
opcache.preload={PWD}/preload_generics.inc
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
// get_declared_classes() does not trigger stamp-on-miss, so presence here
// proves each instantiation was stamped at preload time, not at runtime.
$declared = array_flip(array_merge(get_declared_classes(), get_declared_interfaces()));
foreach (["Vec<int>", "Vec<string>", "Vec<float>", "Vec<DateTime>", "Vec<Vec<int>>",
          "PCollection<float>", "PCollection<DateTime>"] as $c) {
    printf("%-21s %s\n", $c, isset($declared[$c]) ? "preloaded" : "MISSING");
}

$f = new FloatList();
$f->push(1.5);
var_dump($f->pop());
try { $f->push("x"); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
var_dump($f instanceof PCollection<float>);
var_dump(makeDates() instanceof Vec<DateTime>);
var_dump(makeDates() instanceof PCollection<DateTime>);
var_dump(get_class(makeDates()));
?>
--EXPECTF--
Vec<int>              preloaded
Vec<string>           preloaded
Vec<float>            preloaded
Vec<DateTime>         preloaded
Vec<Vec<int>>         preloaded
PCollection<float>    preloaded
PCollection<DateTime> preloaded
float(1.5)
Vec<float>::push(): Argument #1 ($item) must be of type float, string given, called in %s on line %d
bool(true)
bool(true)
bool(true)
string(13) "Vec<DateTime>"
