--TEST--
Structs: readonly struct marks every property readonly
--FILE--
<?php

readonly struct Span {
    public function __construct(
        public int $start,
        public int $length,
    ) {}
}

struct Partial {
    public function __construct(
        public readonly int $fixed,
        public int $loose,
    ) {}
}

$s = new Span(0, 5);
var_dump($s->start, $s->length);

$rc = new ReflectionClass(Span::class);
var_dump($rc->isReadOnly());
var_dump($rc->getProperty('start')->isReadOnly());
var_dump($rc->getProperty('length')->isReadOnly());

$rp = new ReflectionClass(Partial::class);
var_dump($rp->isReadOnly());
var_dump($rp->getProperty('fixed')->isReadOnly());
var_dump($rp->getProperty('loose')->isReadOnly());

try {
    $s->start = 1;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECT--
int(0)
int(5)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
Cannot modify readonly property Span::$start
