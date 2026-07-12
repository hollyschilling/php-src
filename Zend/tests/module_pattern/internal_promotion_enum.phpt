--TEST--
internal works on promoted constructor properties and enum members
--FILE--
<?php
eval(<<<'PHP'
namespace Acme;

module Acme\Kernel;

class Box {
    public function __construct(internal int $size) {}
    public function grow(): int { return ++$this->size; }
}

enum Mode {
    case On;
    internal const HIDDEN = 'h';
    internal function tag(): string { return 'tagged'; }
    public function runTag(): string { return $this->tag(); }
}

class Runner {
    public function makeBox(int $n): Box { return new Box($n); }
    public function mode(): Mode { return Mode::On; }
}
PHP);

// Obtain instances dynamically / via a same-module runner (bare class
// acquisition from the null module is gated).
$runnerClass = 'Acme\Runner';
$runner = new $runnerClass();

$b = $runner->makeBox(3);
var_dump($b->grow());       // same class: OK
try {
    var_dump($b->size);
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

$mode = $runner->mode();
var_dump($mode->runTag());  // same class: OK
try {
    $mode->tag();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    var_dump(constant('Acme\Mode::HIDDEN'));
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
int(4)
Cannot access internal property Acme\Box::$size
string(6) "tagged"
Call to internal method Acme\Mode::tag() from global scope
Cannot access internal constant Acme\Mode::HIDDEN
