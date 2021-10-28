<?php

namespace Royal\Middlewares;

use Rashtell\Domain\JSON;
use Rashtell\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ToJsonMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = new Response();
        $json = new JSON();

        $data = $request->getParsedBody();
        $data = isset($data) ? $data : $request->getBody();

        $validJson = $json->jsonFormat($data);

        if ($validJson == NULL) {
            $g = array("error" => array("message" => "The parameter is not a valid object", "status" => "1"));
            return $json->withJsonResponse($response, $g);
        }

        $request->withAttribute("isJson", true);
        // $request->withBody($validJson);

        $response = $handler->handle($request);
        return $response;
    }
}
