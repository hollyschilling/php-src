<?php
interface PackMerger<A, B> { public function merge(A $a, B $b): string; }
class PackZip<...Ts> implements PackMerger<...Ts> {
    public function merge(int $a, string $b): string { return "$a:$b"; }
}
class PackPair<...Tp, R> {
    public function cls(): string { return R::class; }
}
