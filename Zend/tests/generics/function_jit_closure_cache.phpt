--TEST--
Generic function JIT reuses closure specializations without retaining captured objects
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.jit=function
opcache.jit_buffer_size=16M
opcache.protect_memory=1
--SKIPIF--
<?php
if (!isset(opcache_get_configuration()['directives']['opcache.jit'])) die('skip JIT is not available');
?>
--FILE--
<?php
declare(strict_types=1);
class CachedJitFactory<T> {
    private int $tag = 11;
    public function make(int $offset): Closure {
        return function (T $value) use ($offset): array {
            static $calls = 0;
            return [$value, $offset, ++$calls];
        };
    }
    public function keep(T $value): T { return $value; }
    public function capture(object $owner): Closure {
        return static function (T $value) use ($owner): T { return $value; };
    }
    public function scoped(): Closure {
        return function (T $value): array { return [$value, $this->tag]; };
    }
    public function kind<U>(): Closure { return fn(): string => U::class; }
}
class CachedJitScope {}
class CachedJitOther {
    public string $padding = 'wrong';
    private int $tag = 22;
}
$factory = new CachedJitFactory<int>();
$weak = WeakReference::create($factory);
$scope = new CachedJitScope();
$closure = $factory->make(0);
var_dump($closure(42));
$bound = $closure->bindTo($scope);
$bound(42);
$callable = $factory->keep(...);
$callable(42);
$before = opcache_get_status(false)['jit']['buffer_free'];
unset($closure, $bound, $callable);
for ($i = 0; $i < 1000; $i++) {
    $closure = $factory->make($i);
    if ($closure($i) !== [$i, $i, 1]) { throw new Exception('Shared captured/static state'); }
    $bound = $closure->bindTo($scope);
    if ($bound($i) !== [$i, $i, 2]) { throw new Exception('Wrong rebound state'); }
    $callable = $factory->keep(...);
    if ($callable($i) !== $i) { throw new Exception('Wrong callable binding'); }
}
var_dump(opcache_get_status(false)['jit']['buffer_free'] === $before);
// The same lexical scope after rebinding must not merge int and string signatures.
$stringFactory = new CachedJitFactory<string>();
$string = $stringFactory->make(7)->bindTo($scope);
var_dump($string('value'));
try { $string(42); } catch (TypeError $e) { echo "string type enforced\n"; }
try { $bound('wrong'); } catch (TypeError $e) { echo "int type enforced\n"; }
unset($closure, $bound, $callable, $factory);
gc_collect_cycles();
var_dump($weak->get());
$owner = new stdClass();
$capturedWeak = WeakReference::create($owner);
$captured = $stringFactory->capture($owner);
$captured('value');
unset($captured, $owner);
gc_collect_cycles();
var_dump($capturedWeak->get());
$scoped = $stringFactory->scoped();
$scoped('warmup');
var_dump($scoped->call(new CachedJitOther(), 'call'));
$kindInt = $stringFactory->kind<int>();
$kindString = $stringFactory->kind<string>();
var_dump($kindInt(), $kindString());
$beforeBindings = opcache_get_status(false)['jit']['buffer_free'];
for ($i = 0; $i < 100; $i++) {
    if (($stringFactory->kind<int>())() !== 'int'
        || ($stringFactory->kind<string>())() !== 'string'
        || $scoped->call(new CachedJitOther(), 'call') !== ['call', 22]) {
        throw new Exception('Wrong cached method binding or call scope');
    }
}
var_dump(opcache_get_status(false)['jit']['buffer_free'] === $beforeBindings);
?>
--EXPECT--
array(3) {
  [0]=>
  int(42)
  [1]=>
  int(0)
  [2]=>
  int(1)
}
bool(true)
array(3) {
  [0]=>
  string(5) "value"
  [1]=>
  int(7)
  [2]=>
  int(1)
}
string type enforced
int type enforced
NULL
NULL
array(2) {
  [0]=>
  string(4) "call"
  [1]=>
  int(22)
}
string(3) "int"
string(6) "string"
bool(true)
