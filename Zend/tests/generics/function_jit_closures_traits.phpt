--TEST--
Function JIT separates generic closure copies, rebound closures and trait use sites
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
class FunctionJitFactory<T> {
    public function mapper(): Closure {
        return function (T $value): T { return $value; };
    }
}
trait FunctionJitCache<T> {
    public function keep(T $value): T { return $value; }
}
class FunctionJitInts { use FunctionJitCache<int>; }
class FunctionJitStrings { use FunctionJitCache<string>; }
class FunctionJitScope {}

function compiledCall(Closure $call): void {
    $before = opcache_get_status(false)['jit']['buffer_free'];
    var_dump($call());
    var_dump(opcache_get_status(false)['jit']['buffer_free'] < $before);
}
$ints = (new FunctionJitFactory<int>())->mapper();
$strings = (new FunctionJitFactory<string>())->mapper();
compiledCall(fn() => $ints(41));
compiledCall(fn() => $strings('closure'));
// Rebinding after compilation must start with independent entry points.
$rebound = $ints->bindTo(new FunctionJitScope());
compiledCall(fn() => $rebound(42));
var_dump($ints(43), $strings('still string'));
try { $rebound('wrong'); } catch (TypeError $e) { echo "closure type enforced\n"; }
$i = new FunctionJitInts();
$s = new FunctionJitStrings();
compiledCall(fn() => $i->keep(44));
compiledCall(fn() => $s->keep('trait'));
var_dump($i->keep(45));
try { $s->keep(46); } catch (TypeError $e) { echo "trait type enforced\n"; }
?>
--EXPECT--
int(41)
bool(true)
string(7) "closure"
bool(true)
int(42)
bool(true)
int(43)
string(12) "still string"
closure type enforced
int(44)
bool(true)
string(5) "trait"
bool(true)
int(45)
trait type enforced
