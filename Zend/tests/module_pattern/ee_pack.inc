<?php
namespace Acme\Ext;

module Acme\Ext\Pack;          // membership: stamps the extension CE with the module

module Pack {
    export extension Acme\Ext\WidgetHelpers;
}

extension WidgetHelpers on \Widget $w {
    public function shout(): string { return \strtoupper($w->name); }
}
