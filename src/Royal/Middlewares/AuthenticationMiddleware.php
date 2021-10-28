<?php

namespace Royal\Middlewares;

use Royal\Controllers\BaseController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Rashtell\Domain\JSON;
use Royal\Models\BaseModel;
use Rashtell\Domain\CodeLibrary;

class AuthenticationMiddleware implements MiddlewareInterface
{

    function __construct($model)
    {
        $this->model = $model;
    }
    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        $json = new JSON();

        $token = BaseController::getToken($request);

        if (!$token) {
            $request->withAttribute("isAuthenticated", false);

            return $json->withJsonResponse($response, ["statusCode" => 401, "errorMessage" => "Unauthorized user. Please login.", "errorCode" => 1]);
        };

        ["isAuthenticated" => $isAuthenticated, "error" => $error] = $this->model->authenticate($token);

        if (!$isAuthenticated) {
            $request->withAttribute("isAuthenticated", false);

            return $json->withJsonResponse($response, ["statusCode" => 401, "errorMessage" => $error . ". Please login.", "errorCode" => 1]);
        }

        return $handler->handle($request);
    }
}
