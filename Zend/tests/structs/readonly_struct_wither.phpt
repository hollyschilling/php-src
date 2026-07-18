--TEST--
Structs: the readonly workflow -- frozen slots, in-scope clone-with, shallow freeze
--FILE--
<?php

readonly struct Span {
    public function __construct(public int $start, public int $length) {}
    public function withStart(int $start): Span {
        return clone($this, ['start' => $start]);
    }
}

$a = new Span(0, 5);

// clone-with needs set access (readonly implies protected(set)), exactly as
// for readonly classes: updates flow through named wither methods.
try {
    clone($a, ['start' => 10]);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

$b = $a->withStart(10);
var_dump($a->start, $b->start, $b->length);

// The result is itself frozen.
try {
    $b->start = 99;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

// Plain clone remains a legal no-op copy.
$c = clone $a;
var_dump($c->start);

// readonly freezes the struct's slots, not a held object's interior:
// the shallow-freeze rule, matching readonly classes.
class Logger { public int $flushes = 0; }
readonly struct Job {
    public function __construct(public string $name, public Logger $log) {}
}
$j1 = new Job('a', new Logger());
$j2 = $j1;
$j2->log->flushes = 7;
var_dump($j1->log->flushes);

?>
--EXPECT--
Cannot modify protected(set) readonly property Span::$start from global scope
int(0)
int(10)
int(5)
Cannot modify readonly property Span::$start
int(0)
int(7)
