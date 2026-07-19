--TEST--
Generics M1: multiple type parameters, all usable in type positions
--FILE--
<?php
class Pair<K, V> {
    public function __construct(
        private ?K $key = null,
        private ?V $value = null,
    ) {}
    public function key(): ?K { return $this->key; }
    public function value(): ?V { return $this->value; }
}
var_dump(class_exists('Pair', false));
$ctor = new ReflectionMethod('Pair', '__construct');
foreach ($ctor->getParameters() as $p) {
    var_dump((string) $p->getType());
}
?>
--EXPECT--
bool(true)
string(2) "?K"
string(2) "?V"
