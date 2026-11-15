--TEST--
Generic function JIT preserves VM fallback and type checks when its buffer fills
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.file_update_protection=0
opcache.jit=function
opcache.jit_buffer_size=64K
opcache.protect_memory=1
--SKIPIF--
<?php
if (!isset(opcache_get_configuration()['directives']['opcache.jit'])) die('skip JIT is not available');
?>
--FILE--
<?php
declare(strict_types=1);
class BufferJitFactory<T> {
    public function mapper(): Closure {
        return function (T $value, int $count): T {
            $sum = 0;
            for ($i = 0; $i < $count; $i++) { $sum += $i; }
            if ($sum !== 45) { throw new Exception('Wrong sum'); }
            return $value;
        };
    }
}
$start = opcache_get_status(false)['jit']['buffer_free'];
$previous = $start;
$fallback = false;
for ($i = 0; $i < 1000; $i++) {
    // Distinct bindings must still compile independently and eventually fill
    // a small buffer; recreating one binding now reuses its compilation.
    eval("class BufferJitType$i {}");
    $type = "BufferJitType$i";
    $factoryType = "BufferJitFactory<$type>";
    $factory = new $factoryType();
    $value = new $type();
    $closure = $factory->mapper();
    if ($closure($value, 10) !== $value) { throw new Exception('Wrong value'); }
    $free = opcache_get_status(false)['jit']['buffer_free'];
    if ($free === $previous) { $fallback = true; }
    $previous = $free;
}
var_dump($free < $start, $fallback);
try { $closure('wrong', 10); } catch (TypeError $e) { echo "fallback type enforced\n"; }
?>
--EXPECT--
bool(true)
bool(true)
fallback type enforced
