--TEST--
Structs: extends is not permitted (structs are implicitly final and root)
--FILE--
<?php

class Base {}

struct P extends Base {
}

?>
--EXPECTF--
Parse error: syntax error, unexpected token "extends", expecting "{" in %s on line %d
