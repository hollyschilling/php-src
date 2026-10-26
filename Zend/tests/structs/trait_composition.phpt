--TEST--
Structs: traits compose, and the wither idiom is not rejected at composition time
--FILE--
<?php

trait Movable {
    public function moved(float $dx, float $dy): static {
        $this->x += $dx;
        $this->y += $dy;
        return $this;
    }
}

trait Tagged {
    public string $tag = 'none';
}

struct Point {
    use Movable;
    use Tagged;

    public function __construct(public float $x, public float $y) {}
}

$p = new Point(1.0, 2.0);
$q = $p->moved(1.0, 1.0);
var_dump($q->x, $q->y, $q->tag);

?>
--EXPECT--
float(2)
float(3)
string(4) "none"
