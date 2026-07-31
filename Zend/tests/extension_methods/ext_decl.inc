<?php

class Vec {
    public function __construct(public array $v) {}
}

extension VecUtils on Vec $vec {
    public function sum(): int { return array_sum($vec->v); }
}

function vec_sum_from_declaring_file(Vec $v): int {
    /* This frame's file declared VecUtils, so the extension is visible here
     * regardless of whether the caller imported it. */
    return $v->sum();
}
