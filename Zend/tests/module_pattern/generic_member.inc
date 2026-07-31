<?php
namespace Acme;

module Acme\Kernel;

module Kernel {
    export Acme\Box;
}

struct Box<T> {
    const LABEL = "boxlabel";
    public function __construct(public T $value) {}
    public static function of(T $v): static { return new static($v); }
    public function get(): T { return $this->value; }
}
