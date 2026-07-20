--TEST--
Surfaces: grouped imports with per-member grant clauses, aliased grants, grant-only reuse
--FILE--
<?php
namespace App\Events {
    class Dispatcher {
        surface Emitter;
        surface[Emitter] function emit(string $e): string { return "emitted:$e"; }
    }
    class Queue {
        surface Producer;
        surface Consumer;
        surface[Producer, Consumer] function depth(): int { return 0; }
    }
    class Logger {
        public function log(string $m): string { return "log:$m"; }
    }
}

namespace Consumer {
    use App\Events\{
        Dispatcher as Bus with surface[Emitter],
        Queue with surface[Producer, Consumer],
        Logger,
    };

    function run(): void {
        $bus = new Bus();
        echo $bus->emit("boot"), "\n";
        $q = new Queue();
        echo "depth=", $q->depth(), "\n";
        echo (new Logger)->log("done"), "\n";
    }
    run();

    // Grant-only form: the name is already imported; this must not conflict.
    use Bus with surface[Emitter];
    echo (new Bus)->emit("again"), "\n";
}
?>
--EXPECT--
emitted:boot
depth=0
log:done
emitted:again
