<?php
require __DIR__ . '/preload_jit_request.inc';

class RequestJitFactory<T> {
    public function mapper(): Closure {
        return function (T $value): T { return $value; };
    }
}
trait RequestJitTrait<T> {
    public function keep(T $value): T { return $value; }
}
class RequestJitInts { use RequestJitTrait<int>; }
class RequestJitStrings { use RequestJitTrait<string>; }

$intClosure = (new RequestJitFactory<int>())->mapper();
$stringClosure = (new RequestJitFactory<string>())->mapper();
if ($intClosure(42) !== 42 || $stringClosure('closure') !== 'closure'
    || (new RequestJitInts())->keep(43) !== 43
    || (new RequestJitStrings())->keep('trait') !== 'trait') {
    throw new Exception('Wrong request-local generic binding');
}
try {
    $intClosure('wrong');
    throw new Exception('Missing type check');
} catch (TypeError $e) {
}
echo "request-local ok\n";
$beforeCopies = opcache_get_status(false)['jit']['buffer_free'];
$factory = new RequestJitFactory<int>();
for ($i = 0; $i < 100; $i++) {
    if (($factory->mapper())($i) !== $i) {
        throw new Exception('Wrong cached closure result');
    }
}
if (opcache_get_status(false)['jit']['buffer_free'] !== $beforeCopies) {
    throw new Exception('Repeated closure compilation within request');
}
