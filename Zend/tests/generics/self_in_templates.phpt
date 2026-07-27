--TEST--
Generics: self in a template denotes the current instantiation
--FILE--
<?php
class Vec<T> {
    public ?self $next = null;
    public function grow(): self { return new self(); }
    public function tag(): string { return self::class; }
    public function stat(): static { return new static(); }
    public function take(self $other): string { return get_class($other); }
}
$v = new Vec<int>();
var_dump($v->tag());
var_dump(get_class($v->grow()));
var_dump(get_class($v->stat()));
var_dump($v->take(new Vec<int>()));
$v->next = new Vec<int>();
var_dump(get_class($v->next));

// Cross-instantiation: self means THIS instantiation, so Vec<string> is rejected.
try {
    $v->take(new Vec<string>());
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
try {
    $v->next = new Vec<string>();
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}

// A subclass of an instantiation still satisfies self on the parent clone.
class Grown extends Vec<int> {}
$g = new Grown();
var_dump($v->take($g));
var_dump(get_class($g->grow()));   // new self() in inherited body: declaring scope
var_dump($g->tag());
?>
--EXPECTF--
string(8) "Vec<int>"
string(8) "Vec<int>"
string(8) "Vec<int>"
string(8) "Vec<int>"
string(8) "Vec<int>"
Vec<int>::take(): Argument #1 ($other) must be of type Vec<int>, Vec<string> given, called in %s on line %d
Cannot assign Vec<string> to property Vec<int>::$next of type ?self
string(5) "Grown"
string(8) "Vec<int>"
string(8) "Vec<int>"
