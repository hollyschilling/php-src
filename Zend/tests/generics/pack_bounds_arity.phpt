--TEST--
Generics packs: elementwise bounds and minimum arity
--FILE--
<?php
interface Tagged {}
class TA implements Tagged {}
class TB implements Tagged {}
class TC {}

class Bag<...Ts implements Tagged> {}

new Bag<TA, TB>();
echo "bounds ok\n";
new Bag<TA>();
echo "single ok\n";

try {
    new Bag<TA, TC>();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

class Pair<...Tp, R> {}
try {
    new Pair<int>();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

// A packless template keeps the exact-arity message.
class Solo<T> {}
try {
    new Solo<int, string>();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
bounds ok
single ok
TC does not satisfy the bound Tagged of type parameter Ts on Bag
Generic class Pair expects at least 2 type arguments, 1 given
Generic class Solo expects 1 type argument, 2 given
