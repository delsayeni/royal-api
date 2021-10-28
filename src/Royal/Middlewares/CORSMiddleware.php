<?php

namespace Royal\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

class CORSMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // $routeContext = RouteContext::fromRequest($request);
        // $routingResults = $routeContext->getRoutingResults();
        // $methods = $routingResults->getAllowedMethods();
        // $requestHeaders = $request->getHeaderLine("Access-Control-Request-Headers");

        // $response = $handler->handle($request);

        // $response = $response->withHeader("Access-Control-Allow-Origin", "*");
        // $response = $response->withHeader("Access-Control-Allow-Methods", implode(",", $methods));
        // $response = $response->withHeader("Access-Control-Allow-Headers", $requestHeaders);

        // // Optional: Allow Ajax CORS requests with Authorization header
        // $response = $response->withHeader("Access-Control-Allow-Credentials", "true");

        // return $response;


        $response = $handler->handle($request);

        return $response
            ->withHeader("Access-Control-Allow-Origin", "*")
            ->withHeader("Access-Control-Allow-Headers", "X-Requested-With, Content-Type, Accept, Origin, Authorization")
            ->withHeader("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, PATCH, OPTIONS");
    }
}
