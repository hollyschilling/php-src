--TEST--
Structs: nested writes through private(set)/readonly struct properties follow the array rule
--FILE--
<?php

struct Foo {
    public function __construct(public int $value) {}
}

struct Bar {
    public private(set) Foo $foo;
    public function __construct(int $innerValue) {
        $this->foo = new Foo($innerValue);
    }
    public function setInner(int $v): Bar {
        $this->foo->value = $v;   // set access granted in class scope
        return $this;
    }
}

$bar = new Bar(10);

// A struct is a value: writing through the property writes the property.
// Without set access this must fail like an array element write would --
// never silently discard, never mutate.
try {
    $bar->foo->value = 5;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
var_dump($bar->foo->value);

// Compound assignment and increment take the same path.
try {
    $bar->foo->value += 1;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    $bar->foo->value++;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

// Dynamic property names route through the object handler; same rule.
$name = 'foo';
try {
    $bar->{$name}->value = 5;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

// In class scope the write is permitted and copy-on-write still applies:
// the updated copy is separate from the original.
$updated = $bar->setInner(99);
var_dump($bar->foo->value, $updated->foo->value);

// readonly gives the readonly flavor of the same error.
class Holder {
    public readonly Foo $foo;
    public function __construct() { $this->foo = new Foo(1); }
}
$h = new Holder();
try {
    $h->foo->value = 5;
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
var_dump($h->foo->value);

?>
--EXPECT--
Cannot indirectly modify private(set) property Bar::$foo from global scope
int(10)
Cannot indirectly modify private(set) property Bar::$foo from global scope
Cannot indirectly modify private(set) property Bar::$foo from global scope
Cannot indirectly modify private(set) property Bar::$foo from global scope
int(10)
int(99)
Cannot indirectly modify readonly property Holder::$foo
int(1)
