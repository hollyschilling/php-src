--TEST--
Named extensions are lexically gated: not visible without use extension; visibility follows the executing frame's file
--FILE--
<?php
require __DIR__ . '/ext_decl.inc';

$v = new Vec([1, 2, 3]);

/* This file never imported VecUtils. */
try {
    $v->sum();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

/* But a function whose code lives in the declaring file sees it. */
var_dump(vec_sum_from_declaring_file($v));

/* eval()'d code is its own compilation unit and may import it. */
var_dump(eval('use extension VecUtils; return (new Vec([4, 5]))->sum();'));

/* ...without leaking visibility back into this file. */
try {
    $v->sum();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Call to undefined method Vec::sum()
int(6)
int(9)
Call to undefined method Vec::sum()
