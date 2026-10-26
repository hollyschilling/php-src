--TEST--
Turbofish '::<' at method call sites; multi-arg type lists require it
--FILE--
<?php
class Seq {
    public function pair<U, V>(): string { return U::class . '|' . V::class; }
    public static function stat<U, V>(): string { return U::class . '&' . V::class; }
    public function one<U>(): string { return U::class; }
}
class Name {} class Length {}

$s = new Seq();

// multi-arg lists: the explicit form is required (plain '<' with a
// top-level comma before '(' stays a comparison shape and cannot parse)
var_dump($s->pair::<Name, Length>());
var_dump(Seq::stat::<Name, Length>());

// variable class name receiver
$cls = 'Seq';
var_dump($cls::stat::<Length, Name>());

// single-arg call sites work in BOTH spellings
var_dump($s->one<Name>());
var_dump($s->one::<Name>());

// the declined plain-'<' multi-arg spelling is a parse error, not a misparse
try {
    eval('$s->pair<Name, Length>();');
    echo "no error\n";
} catch (ParseError $e) {
    echo "ParseError\n";
}
?>
--EXPECT--
string(11) "Name|Length"
string(11) "Name&Length"
string(11) "Length&Name"
string(4) "Name"
string(4) "Name"
ParseError
