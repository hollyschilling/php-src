<?php
namespace Acme;

module Acme\Kernel;

module Kernel {
    export Acme\Widget;
    export Acme\Bag;
}

class Widget { public string $n = "w"; }
class Bag { public string $b = "b"; }
