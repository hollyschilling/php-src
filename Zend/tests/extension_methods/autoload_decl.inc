<?php
namespace Vendor;

extension Greeter on \App\Widget $w {
    public function greet(): string { return "hi, " . $w->name; }
}
