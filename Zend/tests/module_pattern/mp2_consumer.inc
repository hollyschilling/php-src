<?php
namespace App;

use module Acme\Http\Kernel;

class Application
{
    public function handle(): int
    {
        $router = new Kernel:>Router();
        $req = new Kernel:>Request();
        $resp = $router->dispatch($req);
        return $resp->status;
    }

    public function names(): array
    {
        return [Kernel:>Request::class, Kernel:>Router::class];
    }

    public function checkInstance(): bool
    {
        $r = new Kernel:>Request();
        return $r instanceof Kernel:>Request && $r instanceof \Acme\Http\Request;
    }

    public function legacyNote(): string
    {
        return (new Kernel:>LegacyRequest())->note;
    }

    public function staticSurface(): array
    {
        return [Kernel:>Router::VERSION, Kernel:>Router::$dispatches];
    }

    public function reachInternalClass(): void
    {
        // RouteCompiler is a member but not exported; resolve via string to
        // show the runtime gate (compile-time :> would not even parse-resolve).
        new \Acme\Http\Routing\RouteCompiler();
    }
}
