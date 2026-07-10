<?php
class RateLimiter {
    surface Testing;

    private ?string $clock = null;

    public function attempt(string $key): bool { return $this->clock === null; }

    surface[Testing] function setClock(string $clock): void {
        $this->clock = $clock;
    }
}
