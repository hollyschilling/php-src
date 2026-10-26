--TEST--
Generics lexer soundness: valid PHP 8.x comparison shapes are never claimed as generics
--FILE--
<?php
const A = 10, B = 2, C = 1;
function f($a, $b) { var_dump($a, $b); }

// Comma-separated comparisons inside call args: '(' follow with a top-level
// comma must decline, leaving two comparison arguments.
f(A < B, C > (5));
f(A<B, C>(5));

// '>' follow must decline: A < (B >> C), shift binds tighter.
var_dump(A < B >> C);

// Array context, same shape.
var_dump([A < B, C > (5)]);

// '$' follow with commas: two comparisons against a variable.
$x = 3;
f(A < B, C > $x);
?>
--EXPECT--
bool(false)
bool(false)
bool(false)
bool(false)
bool(false)
array(2) {
  [0]=>
  bool(false)
  [1]=>
  bool(false)
}
bool(false)
bool(false)
