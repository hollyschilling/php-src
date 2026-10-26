--TEST--
Generics: templates extending Exception — stamped instances construct, throw, and report correctly (regression: inherited internal prop_info rebind)
--FILE--
<?php
class MyEx<T> extends Exception {
    public function __construct(public mixed $payload = null, string $msg = "") {
        parent::__construct($msg);
    }
}

// Construction alone exercised the crash: the exception machinery writes the
// base class's protected properties from the Exception scope, which requires
// inherited property_info entries to keep their original declaring class.
$e = new MyEx<int>(42, "boom");
var_dump(get_class($e), $e->getMessage(), $e->payload);
var_dump($e->getLine() > 0, $e->getFile() !== "");

// Throw/catch, including generic catches and selectivity between instantiations.
try {
    throw new MyEx<int>(7, "seven");
} catch (MyEx<int> $c) {
    var_dump($c->payload, $c->getMessage());
}
try {
    try {
        throw new MyEx<string>("s");
    } catch (MyEx<int> $c) {
        echo "WRONG\n";
    }
} catch (MyEx<string> $c) {
    echo "selective ok\n";
}

// getTrace / __toString exercise more of the base machinery.
try {
    throw new MyEx<int>(1, "t");
} catch (Exception $c) {
    var_dump(is_array($c->getTrace()));
    var_dump(str_contains((string) $c, "MyEx<int>"));
}
?>
--EXPECT--
string(9) "MyEx<int>"
string(4) "boom"
int(42)
bool(true)
bool(true)
int(7)
string(5) "seven"
selective ok
bool(true)
bool(true)
