<?php
/**
 * Real-world "tax" workload: nikic/PHP-Parser lexing, parsing and traversing
 * its own source tree.
 *
 * Not one line of this uses generics — that is the point. Whatever difference
 * shows up between the baseline build and the generics build is what the branch
 * costs an application that never opts in, which is the number the RFC has to
 * be able to state. PHP-Parser is a good stand-in for such an application: it is
 * large, heavily object-oriented, allocation-heavy, and does the string and
 * array work typical of real PHP.
 *
 * Env: REALWORLD_WORK (checkout root), BENCH_FILES (files to parse per request).
 */
declare(strict_types=1);

$work = getenv('REALWORLD_WORK') ?: (sys_get_temp_dir() . '/generics-bench-realworld');
$lib  = $work . '/php-parser/lib';

spl_autoload_register(static function (string $class) use ($lib): void {
    if (str_starts_with($class, 'PhpParser\\')) {
        $path = $lib . '/' . str_replace('\\', '/', $class) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

$limit = (int) (getenv('BENCH_FILES') ?: 120);

$files = [];
$it    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($lib));
foreach ($it as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
sort($files);                       // deterministic order across runs
$files = array_slice($files, 0, $limit);

$parser    = (new PhpParser\ParserFactory())->createForNewestSupportedVersion();
$traverser = new PhpParser\NodeTraverser(new PhpParser\NodeVisitor\NameResolver());

$nodes = 0;
foreach ($files as $path) {
    $stmts = $parser->parse(file_get_contents($path));
    if ($stmts === null) {
        continue;
    }
    $stmts = $traverser->traverse($stmts);

    $stack = $stmts;
    while ($stack) {
        $node = array_pop($stack);
        $nodes++;
        foreach ($node->getSubNodeNames() as $name) {
            $sub = $node->$name;
            if ($sub instanceof PhpParser\Node) {
                $stack[] = $sub;
            } elseif (is_array($sub)) {
                foreach ($sub as $child) {
                    if ($child instanceof PhpParser\Node) {
                        $stack[] = $child;
                    }
                }
            }
        }
    }
}

echo "files=", count($files), " nodes=$nodes\n";
