--TEST--
Named scalar extensions are lexically gated like class-targeted ones
--FILE--
<?php
extension Str on string $s {
    public function shout(): string { return strtoupper($s) . "!"; }
}

/* Declaring position imports itself. */
var_dump("hi"->shout());

/* eval() is its own compilation unit: no import, no visibility... */
var_dump(eval('try { "hi"->shout(); } catch (Error $e) { return $e->getMessage(); }'));

/* ...unless it imports. */
var_dump(eval('use extension Str; return "hi"->shout();'));
?>
--EXPECT--
string(3) "HI!"
string(43) "Call to a member function shout() on string"
string(3) "HI!"
