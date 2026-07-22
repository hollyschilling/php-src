--TEST--
Generic methods: templates persist through opcache and stamp from the cached op_arrays
--INI--
opcache.enable=1
opcache.enable_cli=1
--FILE--
<?php
require __DIR__ . '/method_generics_defs.inc';

$s = new MGSeq<int>();
$prices = $s->fill(3)->map<MGPrice>(fn(int $c) => new MGPrice($c));
var_dump(get_class($prices), $prices->count());
var_dump(get_class(MGSeq<int>::of<MGPrice>(new MGPrice(1))));
var_dump($s->name<MGPrice>());
?>
--EXPECT--
string(14) "MGSeq<MGPrice>"
int(3)
string(14) "MGSeq<MGPrice>"
string(7) "MGPrice"
