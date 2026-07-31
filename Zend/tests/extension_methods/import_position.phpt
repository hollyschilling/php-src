--TEST--
use extension affects code compiled after it, like other use imports
--FILE--
<?php
require __DIR__ . '/ext_decl.inc';

function before_import(Vec $v): int {
    return $v->sum();
}

use extension VecUtils;

function after_import(Vec $v): int {
    return $v->sum();
}

try {
    before_import(new Vec([1, 2]));
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
var_dump(after_import(new Vec([1, 2])));
?>
--EXPECT--
Call to undefined method Vec::sum()
int(3)
