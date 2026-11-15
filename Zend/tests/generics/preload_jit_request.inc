<?php
declare(strict_types=1);
$ints = new PreloadJitBox<int>();
$strings = new PreloadJitBox<string>();
$methods = new PreloadJitMethods();
$before = opcache_get_status(false)['jit']['buffer_free'];
if ($ints->keep(42, 300) !== 42 || $strings->keep('value', 300) !== 'value'
    || $ints->pick<int>(43, 300) !== 43 || $ints->pick<string>('pick', 300) !== 'pick'
    || $methods->keep<int>(44, 300) !== 44 || $methods->keep<string>('method', 300) !== 'method') {
    throw new Exception('Wrong generic binding');
}
if (opcache_get_status(false)['jit']['buffer_free'] < $before) {
    echo "compiled\n";
}
echo "request ok\n";
