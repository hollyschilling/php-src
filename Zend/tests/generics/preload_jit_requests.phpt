--TEST--
Preloaded generic JIT metadata remains valid across repeated CGI requests
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY === 'Windows') die('skip Preloading is not supported on Windows');
if (!isset(opcache_get_configuration()['directives']['opcache.jit'])) die('skip JIT is not available');
if (!getenv('TEST_PHP_CGI_EXECUTABLE')) die('skip CGI is not available');
?>
--FILE--
<?php
$command = [
    getenv('TEST_PHP_CGI_EXECUTABLE'), '-n',
    '-d', 'opcache.enable=1',
    '-d', 'opcache.protect_memory=1',
    '-d', 'opcache.jit=tracing',
    '-d', 'opcache.jit_buffer_size=16M',
    '-d', 'opcache.jit_hot_loop=2',
    '-d', 'opcache.jit_hot_func=2',
    '-d', 'opcache.preload=' . __DIR__ . '/preload_jit_defs.inc',
    '-T10',
];
$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, [
    'SCRIPT_FILENAME' => __DIR__ . '/preload_jit_request.inc',
    'REDIRECT_STATUS' => '1',
]);
$output = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
var_dump(proc_close($process));
var_dump(substr_count($output, "request ok\n"));
var_dump(str_contains($output, "compiled\n"));
// CGI's -T option writes timing information to stderr on successful runs.
var_dump((bool) preg_match('/^\s*Elapsed time: [0-9.]+ sec\s*$/D', $errors));
?>
--EXPECT--
int(0)
int(10)
bool(true)
bool(true)
