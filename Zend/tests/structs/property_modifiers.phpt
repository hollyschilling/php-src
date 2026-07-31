--TEST--
Structs: visibility, asymmetric visibility and property hooks
--FILE--
<?php

struct A {
    public private(set) int $locked;
    private string $secret;
    protected float $prot;

    public int $doubled {
        get => $this->locked * 2;
        set (int $v) { $this->locked = $v; }
    }

    public function __construct(int $n) {
        $this->locked = $n;
        $this->secret = 'hidden';
        $this->prot = 1.0;
    }

    public function reveal(): string { return $this->secret; }
}

$a = new A(21);
var_dump($a->locked, $a->doubled, $a->reveal());

// A set hook may write the backing property from inside the struct.
$a->doubled = 5;
var_dump($a->locked);

try {
    $a->locked = 9;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

try {
    $a->secret;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECT--
int(21)
int(42)
string(6) "hidden"
int(5)
Cannot modify private(set) property A::$locked from global scope
Cannot access private property A::$secret
