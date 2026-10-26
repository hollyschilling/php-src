<?php
namespace Vendor;

module Vendor\Toolkit;

use module Acme\Kernel;
use Acme\Widget;

class Spy
{
    public static function snoop(Widget $w): string {
        return $w->step();
    }
    public static function snoopConst(): string {
        return Kernel:>Widget::SECRET;
    }
    public static function snoopProp(Widget $w): string {
        return $w->token;
    }
}
