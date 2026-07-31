--TEST--
Module definition: two exports with the same alias is a compile error
--FILE--
<?php
namespace A;

module Two {
    export A\X;
    export B\X;
}
?>
--EXPECTF--
Fatal error: Module A\Two exports two members named X; use 'as' to disambiguate in %s on line %d
