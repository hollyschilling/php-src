--TEST--
`mutating` stays usable as a function, method, and constant name
--FILE--
<?php

function mutating(int $n): int { return $n + 1; }
var_dump(mutating(41));

class C {
    const mutating = 'k';
    public function mutating(): int { return 7; }
}
var_dump((new C)->mutating());
var_dump(C::mutating);

$f = 'mutating';
var_dump($f(1));

?>
--EXPECT--
int(42)
int(7)
string(1) "k"
int(2)
