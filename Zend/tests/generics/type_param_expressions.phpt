--TEST--
Generics M3: new T(), T::class, instanceof T, T::CONST and T::method() resolve via the scope binding
--FILE--
<?php
class Registry<T extends Exception> {
    public function make(string $msg): T { return new T($msg); }
    public function name(): string { return T::class; }
    public function check(object $o): bool { return $o instanceof T; }
    public function code(): int { return T::CODE; }
}
class MyError extends Exception { const CODE = 42; }

$r = new Registry<MyError>();
$e = $r->make("boom");
var_dump(get_class($e));
var_dump($e->getMessage());
var_dump($r->name());
var_dump($r->check($e));
var_dump($r->check(new stdClass));
var_dump($r->code());

$r2 = new Registry<RuntimeException>();
var_dump(get_class($r2->make("x")));
var_dump($r2->name());

class WithMake { public static function make(): static { return new static(); } }
class Del<T> { public function go(): object { return T::make(); } }
$d = new Del<WithMake>();
var_dump(get_class($d->go()));
?>
--EXPECT--
string(7) "MyError"
string(4) "boom"
string(7) "MyError"
bool(true)
bool(false)
int(42)
string(16) "RuntimeException"
string(16) "RuntimeException"
string(8) "WithMake"
