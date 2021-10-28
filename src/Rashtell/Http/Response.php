<?php

namespace Rashtell\Http;

use Slim\Psr7\Response as ResponseBase;

class Response extends ResponseBase{

    public function withJson ($array){
        $json = json_encode($array);

        $clone = clone $this;
        $clone->withHeader("Accepts", "application/json");
        $clone->withHeader("Content-Type", "application/json");
        $clone->getBody()->write($json);

        return $clone;
    }
}

