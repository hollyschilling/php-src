--TEST--
Surfaces: interface binding is nominal; interface-faced members are runtime-ungated (v0)
--FILE--
<?php
interface ProducerInterface {
    public function ingest(string $value): void;
}

class Queue {
    surface Producer implements ProducerInterface;
    surface Consumer;

    private array $items = [];

    surface[Producer] function ingest(string $value): void { $this->items[] = $value; }
    surface[Consumer] function next(): ?string { return array_shift($this->items); }
}

$q = new Queue();
var_dump($q instanceof ProducerInterface);

// Interface-typed use is free (the conversion gate is deferred in this
// prototype, so members implementing a surface-bound interface method are
// exempt from the runtime member gate).
function sink(ProducerInterface $p): void { $p->ingest("x"); }
sink($q);
echo "sink ok\n";
$q->ingest("direct"); // ungated for the same reason (documented v0 semantics)
echo "direct ok\n";

// Members without an interface face stay fully gated.
try { $q->next(); } catch (Error $e) { echo "consumer denied\n"; }

function drain(Queue $q): void {
    use Queue with surface[Consumer];
    while (($v = $q->next()) !== null) { echo "drained: $v\n"; }
}
drain($q);
?>
--EXPECT--
bool(true)
sink ok
direct ok
consumer denied
drained: x
drained: direct
