<?php
namespace Acme\Support;

module Acme\Kernel;

use Acme\Widget;

class Helper
{
    public static function poke(Widget $w): array {
        $w->token = 'poked';
        Widget::$counter++;
        return [$w->step(), Widget::SECRET, $w->token, Widget::$counter];
    }
}
