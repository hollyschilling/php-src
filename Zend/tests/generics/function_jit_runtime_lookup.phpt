--TEST--
Runtime function JIT analysis must not stamp generic classes in its temporary arena
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.jit=1235
opcache.jit_hot_func=1
opcache.jit_buffer_size=16M
opcache.protect_memory=1
--SKIPIF--
<?php
if (!isset(opcache_get_configuration()['directives']['opcache.jit'])) die('skip JIT is not available');
?>
--FILE--
<?php
class RuntimeJitBox<T> {
    public function __construct(public T $value) {}
}
class RuntimeJitPair<A, B> {
    public static function make(): static { return new static(); }
}
$i = new RuntimeJitBox<int>(42);
var_dump($i->value);
var_dump(RuntimeJitPair<int, string>::make()::class);
$s = new RuntimeJitBox<string>('value');
var_dump($s->value);
var_dump($i instanceof RuntimeJitBox<int>);
?>
--EXPECT--
int(42)
string(26) "RuntimeJitPair<int,string>"
string(5) "value"
bool(true)
