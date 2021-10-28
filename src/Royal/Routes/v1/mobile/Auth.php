<?php

use Slim\Routing\RouteCollectorProxy;

use Royal\Controllers\Mobile\AuthController;
use Royal\Middlewares\AuthenticationMiddleware;


isset($mobileGroup) && $mobileGroup->group(
    "/auth",
    function (RouteCollectorProxy $authGroup) {

        $authGroup->post(
            "/doregisterconfirm",
            AuthController::class . ":doRegisterConfirm"
        );

        $authGroup->post(
            "/registeruser",
            AuthController::class . ":Register"
        );

        $authGroup->post(
            "/confirmtoken",
            AuthController::class . ":confirmToken"
        );

        $authGroup->post(
            "/login",
            AuthController::class . ":Login"
        );

        $authGroup->post(
            "/doresetpassword",
            AuthController::class . ":resetPassword"
        );

        $authGroup->post(
            "/dochangepassword",
            AuthController::class . ":changePassword"
        );
    }
);
