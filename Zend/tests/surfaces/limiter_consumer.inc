<?php
use RateLimiter with surface[Testing];

function consumerWithGrant(RateLimiter $r): void {
    $r->setClock('frozen');
    echo "granted\n";
}
