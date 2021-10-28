<?php

use Royal\Controllers\HelperController;
use Slim\Routing\RouteCollectorProxy;

use Royal\Controllers\IranlowoController;
use Royal\Middlewares\AuthenticationMiddleware;
use Psr\Http\Message\UploadedFileInterface;

use function PHPSTORM_META\type;

/**
 * Iranlowo priviledged
 */
isset($v1Group) && $v1Group->group(
    "/iranlowos",
    function (RouteCollectorProxy $iranlowoGroup) {
    }
)
    // ->addMiddleware(new AuthenticationMiddleware((new IranlowoModel())))
;

/**
 * No auth
 * Iranlowo priviledged
 */
isset($v1Group) && $v1Group->group(
    "/iranlowos",
    function (RouteCollectorProxy $iranlowoGroup) {
    }
);

/**
 * Admin priviledged
 */
isset($adminGroup) && $adminGroup->group(
    "",
    function (RouteCollectorProxy $iranlowoGroup) {
        $iranlowoGroup->post("/upload/media", HelperController::class . ':uploadMedias');
    }
);
