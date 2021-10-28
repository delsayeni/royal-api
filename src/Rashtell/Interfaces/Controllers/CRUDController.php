<?php

namespace Rashtell\Interfaces\Controllers;

use Rashtell\Http\Response;
use Slim\Psr7\Request;

interface CRUDController
{
    public function createSelf(Request $request, Response $response): Response;

    public function getALL(Request $request, Response $response): Response;

    public function getOne(Request $request, Response $response): Response;

    public function update(Request $request, Response $response): Response;

    public function delete(Request $request, Response $response): Response;
}
