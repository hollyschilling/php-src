--TEST--
Generics packs: pack templates and spread refs persist through opcache and restamp correctly
--INI--
opcache.enable=1
opcache.enable_cli=1
--FILE--
<?php
require __DIR__ . '/pack_defs.inc';

$z = new PackZip<int, string>();
var_dump($z->merge(1, "x"));
var_dump($z instanceof PackMerger<int,string>);

$p = new PackPair<int, string, DateTimeImmutable>();
var_dump($p->cls());
?>
--EXPECT--
string(3) "1:x"
bool(true)
string(17) "DateTimeImmutable"
