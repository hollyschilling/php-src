--TEST--
:> with an unknown prefix is a compile error
--FILE--
<?php
class T1 {
    public function f() {
        return new Ghost:>Thing();
    }
}
?>
--EXPECTF--
Fatal error: No module imported with name "Ghost" (missing 'use module'?) in %s on line %d
