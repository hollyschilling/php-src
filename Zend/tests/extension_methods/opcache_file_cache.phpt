--TEST--
use extension imports survive opcache file_cache (execute without recompiling)
--SKIPIF--
<?php
if (!extension_loaded('Zend OPcache')) die('skip opcache required');
if (!getenv('TEST_PHP_EXECUTABLE')) die('skip TEST_PHP_EXECUTABLE not set');
?>
--FILE--
<?php
$php = getenv('TEST_PHP_EXECUTABLE');
$dir = __DIR__ . '/opcache_file_cache_tmp';
@mkdir($dir);

$cmd = escapeshellarg($php)
	. ' -d opcache.enable_cli=1 -d opcache.file_cache_only=1'
	. ' -d opcache.file_cache=' . escapeshellarg($dir)
	. ' ' . escapeshellarg(__DIR__ . '/file_cache_consumer.inc') . ' 2>&1';

/* First run compiles and serializes to the file cache; the second run is a
 * fresh process that loads the compiled script from disk without compiling,
 * so imports must travel with the op_arrays. */
echo "run1: ", trim(shell_exec($cmd)), "\n";
echo "run2: ", trim(shell_exec($cmd)), "\n";
?>
--CLEAN--
<?php
$dir = __DIR__ . '/opcache_file_cache_tmp';
if (is_dir($dir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}
?>
--EXPECT--
run1: int(6)
run2: int(6)
