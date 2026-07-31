--TEST--
Structs: implements is permitted and instanceof holds
--FILE--
<?php

interface HasArea {
    public function area(): float;
}

struct Rect implements HasArea, Countable, JsonSerializable {
    public function __construct(public float $w, public float $h) {}

    public function area(): float { return $this->w * $this->h; }
    public function count(): int { return 2; }
    public function jsonSerialize(): mixed { return ['sides' => $this->count()]; }
}

$r = new Rect(3.0, 4.0);
var_dump($r instanceof HasArea, $r instanceof Countable);
var_dump($r->area());
var_dump(count($r));
var_dump(json_encode($r));

function takesArea(HasArea $a): float { return $a->area(); }
var_dump(takesArea($r));

?>
--EXPECT--
bool(true)
bool(true)
float(12)
int(2)
string(11) "{"sides":2}"
float(12)
