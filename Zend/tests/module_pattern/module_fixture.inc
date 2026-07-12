<?php
namespace Acme;

module Acme\Kernel;

module Kernel {
    export Acme\Widget;
    export Acme\Friend;
    export Acme\Support\Helper;
}

class Widget
{
    internal const SECRET = 'k-secret';
    internal string $token = 'tok';
    internal static int $counter = 7;

    internal function step(): string { return 'stepped'; }
    internal static function boot(): string { return 'booted'; }

    public function runStep(): string { return $this->step(); }
}

class Friend
{
    public function useWidget(Widget $w): array {
        return [
            $w->step(),
            Widget::boot(),
            Widget::SECRET,
            $w->token,
            Widget::$counter,
        ];
    }
}
