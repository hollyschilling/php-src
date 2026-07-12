<?php
namespace Acme\Http;

module Kernel {
    export Acme\Http\Request;
    export Acme\Http\Response;
    export Acme\Http\Routing\Router;
    export Acme\Http\Legacy\Request as LegacyRequest;
}
