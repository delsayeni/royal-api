<?php

namespace Rashtell\Interfaces\Controllers;

use Rashtell\Http\Response;
use Slim\Psr7\Request;

interface UserController
{
    public function loginUser(Request $request, Response $response): Response;

    public function createUser(Request $request, Response $response): Response;

    public function getALLUsers(Request $request, Response $response): Response;

    public function getOneUser(Request $request, Response $response): Response;

    public function updateUser(Request $request, Response $response): Response;

    public function deleteUser(Request $request, Response $response): Response;

    public function logoutUser(Request $request, Response $response): Response;
}
