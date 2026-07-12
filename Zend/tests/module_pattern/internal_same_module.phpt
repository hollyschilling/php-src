--TEST--
internal members are accessible from classes in the same module, across files and namespaces
--FILE--
<?php
require __DIR__ . '/module_fixture.inc';
require __DIR__ . '/module_friend_other_ns.inc';

// Dynamic instantiation: the class acquisition gate applies to syntactic
// names only, and this test targets member-level semantics.
$widgetClass = 'Acme\Widget';
$friendClass = 'Acme\Friend';
$w = new $widgetClass();

// Same class
var_dump($w->runStep());

// Same module, same file, different class
var_dump((new $friendClass())->useWidget($w));

// Same module, different file and namespace (dynamic call: ungated)
var_dump(call_user_func('Acme\Support\Helper::poke', $w));
?>
--EXPECT--
string(7) "stepped"
array(5) {
  [0]=>
  string(7) "stepped"
  [1]=>
  string(6) "booted"
  [2]=>
  string(8) "k-secret"
  [3]=>
  string(3) "tok"
  [4]=>
  int(7)
}
array(4) {
  [0]=>
  string(7) "stepped"
  [1]=>
  string(8) "k-secret"
  [2]=>
  string(5) "poked"
  [3]=>
  int(8)
}
