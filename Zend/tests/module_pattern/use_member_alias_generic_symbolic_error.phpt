--TEST--
Aliased generic references in template bodies may not mention type parameters (no compile-time provenance)
--FILE--
<?php
eval('namespace BP; module NL { export BP\NL\Box; }');
eval('namespace BP\NL; module BP\NL;
    class Box<T> { public function __construct(public T $v) {} }');
eval('
use module BP\NL;
use NL:>Box as B;
class Wrapper<T> {
    public function make(): object { return new B<T>(1); }
}');
?>
--EXPECTF--
Fatal error: Module-member-aliased generic references cannot mention type parameters in %s on line %d
