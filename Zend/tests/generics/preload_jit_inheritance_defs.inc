<?php
class PreloadJitParent<T> {
    public int $total = 0;
    public function keep(T $value, int $iterations): T {
        for ($i = 0; $i < $iterations; $i++) {
            $this->total += $i;
        }
        return $value;
    }
}
class PreloadJitChild<T> extends PreloadJitParent<T> {}
class PreloadJitConcrete extends PreloadJitParent<int> {}

class PreloadJitHooks<T> {
    public int $total = 0;
    public T $value {
        get {
            for ($i = 0; $i < 300; $i++) {
                $this->total += $i;
            }
            return $this->value;
        }
    }
    public function __construct(T $value) { $this->value = $value; }
}

function preloadJitMore(
    PreloadJitChild<float> $child,
    PreloadJitHooks<int> $ints,
    PreloadJitHooks<string> $strings,
): void {}
