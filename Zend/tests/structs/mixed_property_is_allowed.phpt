--TEST--
Structs: "mixed" satisfies the typed-shape rule
--FILE--
<?php

struct Box {
    public function __construct(
        public mixed $value,
        public ?int $tag = null,
        public int|string $key = 0,
    ) {}
}

$b = new Box('anything');
var_dump($b->value, $b->tag, $b->key);

?>
--EXPECT--
string(8) "anything"
NULL
int(0)
