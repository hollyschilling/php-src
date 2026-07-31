<?php
namespace Vendor;

extension Greeter on \App\Widget $w {
    public function greet(): string { return "hi, " . $w->name; }
}

extension NumKit on int $n {
    public function twice(): int { return $n * 2; }
}
