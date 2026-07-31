<?php
namespace Acme\Http\Routing;

module Acme\Http\Kernel;

use Acme\Http\Request;
use Acme\Http\Response;

class Router
{
    public const VERSION = 'v1';
    public static int $dispatches = 0;

    public static function create(): Router { return new Router(); }

    public function dispatch(Request $r): Response
    {
        $compiler = new RouteCompiler();
        self::$dispatches++;
        return $compiler->run($r);
    }
}

class RouteCompiler
{
    internal function compile(): string { return 'compiled'; }

    public function run(Request $r): Response
    {
        $this->compile();
        return new Response();
    }
}
