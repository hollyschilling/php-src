<?php
namespace Vendor;

module Vendor\Toolkit;

use Acme\Widget;

class Spy
{
    public static function snoop(Widget $w): string {
        return $w->step();
    }
    public static function snoopConst(): string {
        return Widget::SECRET;
    }
    public static function snoopProp(Widget $w): string {
        return $w->token;
    }
}
