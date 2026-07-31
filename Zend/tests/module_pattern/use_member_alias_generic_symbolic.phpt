--TEST--
Symbolic generic references (new B<T>) carry module provenance: aliased and Mod:> bases work in consumer templates
--FILE--
<?php
eval('namespace BP; module NL { export BP\NL\Box; export BP\NL\Pair; }');
eval('namespace BP\NL; module BP\NL;
    class Box<T> { public function __construct(public T $v) {} }
    class Pair<T> {
        public function __construct() {}
        public function dup(T $v): object { return new Box<T>($v); } // in-module symbolic
    }');

// Consumer template instantiates the aliased module template over its own T.
eval('use module BP\NL; use NL:>Box as B;
    class Cache<T> {
        public function wrap(T $v): object { return new B<T>($v); }
    }');
eval('var_dump(get_class((new \Cache<int>())->wrap(5)));');

// Module-qualified spelling of the same shape.
eval('use module BP\NL;
    class Cache2<T> {
        public function wrap(T $v): object { return new NL:>Box<T>($v); }
    }');
eval('var_dump(get_class((new \Cache2<string>())->wrap("s")));');

// Consumer generic METHOD parameter through the alias.
eval('use module BP\NL; use NL:>Box as B;
    class Util { public function lift<U>(U $v): object { return new B<U>($v); } }
    var_dump(get_class((new Util)->lift::<float>(1.5)));');

// In-module symbolic self-references are unaffected.
eval('use module BP\NL; use NL:>Pair as P;
    var_dump(get_class((new P<int>())->dup(3)));');
?>
--EXPECT--
string(14) "BP\NL\Box<int>"
string(17) "BP\NL\Box<string>"
string(16) "BP\NL\Box<float>"
string(14) "BP\NL\Box<int>"
