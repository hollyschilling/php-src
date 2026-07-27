--TEST--
Surfaces: dynamic access is runtime-checked (deviation from the RFC's static model, which bypasses it)
--FILE--
<?php
class Q {
    surface S;
    surface[S] function poke(): string { return "poked"; }
    surface[S] int $w = 1;
}

$q = new Q();
$m = 'poke';
try { $q->$m(); } catch (Error $e) { echo "dynamic method: denied\n"; }
$p = 'w';
try { $x = $q->$p; } catch (Error $e) { echo "dynamic prop: denied\n"; }
try { call_user_func([$q, 'poke']); } catch (Error $e) { echo "call_user_func: denied\n"; }

function granted(Q $q): void {
    use Q with surface[S];
    $m = 'poke';
    echo "dynamic in granted scope: ", $q->$m(), "\n";
    echo "cuf in granted scope: ", call_user_func([$q, 'poke']), "\n";
}
granted($q);
?>
--EXPECT--
dynamic method: denied
dynamic prop: denied
call_user_func: denied
dynamic in granted scope: poked
cuf in granted scope: poked
