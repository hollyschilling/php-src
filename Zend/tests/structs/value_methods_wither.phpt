--TEST--
Structs: methods bind $this by value; mutations are local; return $this is the wither idiom
--FILE--
<?php

struct Point {
    public function __construct(public float $x, public float $y) {}

    public function length(): float {
        return sqrt($this->x ** 2 + $this->y ** 2);
    }

    public function moved(float $dx, float $dy): Point {
        $this->x += $dx;
        $this->y += $dy;
        return $this;
    }

    public function scaledInPlaceButDiscarded(float $f): void {
        $this->x *= $f;
        $this->y *= $f;
        // no return: the mutation dies with the call
    }
}

$p = new Point(3.0, 4.0);
var_dump($p->length());

// A method mutates its own copy; the receiver is untouched.
$q = $p->moved(1.0, 1.0);
var_dump($p->x, $p->y, $q->x, $q->y);

// A method that mutates without returning has no visible effect.
$p->scaledInPlaceButDiscarded(10.0);
var_dump($p->x, $p->y);

// First-class callable captures the receiver by value.
$r = new Point(1.0, 1.0);
$move = $r->moved(...);
$s = $move(5.0, 5.0);
var_dump($r->x, $s->x);

?>
--EXPECT--
float(5)
float(3)
float(4)
float(4)
float(5)
float(3)
float(4)
float(1)
float(6)
