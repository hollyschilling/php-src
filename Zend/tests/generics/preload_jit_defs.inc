<?php
class PreloadJitBox<T> {
    public int $total = 0;

    public function keep(T $value, int $iterations): T {
        for ($i = 0; $i < $iterations; $i++) {
            $this->total += $i;
        }
        return $value;
    }

    public function pick<U>(U $value, int $iterations): U {
        for ($i = 0; $i < $iterations; $i++) {
            $this->total += $i;
        }
        return $value;
    }
}

class PreloadJitMethods {
    public int $total = 0;

    public function keep<U>(U $value, int $iterations): U {
        for ($i = 0; $i < $iterations; $i++) {
            $this->total += $i;
        }
        return $value;
    }
}

// Signature references make the preload sweep stamp both classes.
function preloadJitBoxes(PreloadJitBox<int> $ints, PreloadJitBox<string> $strings): void {}
