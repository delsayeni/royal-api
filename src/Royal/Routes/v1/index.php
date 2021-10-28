<?php

use Slim\Routing\RouteCollectorProxy;

$vGroup->group(
    "/v1",
    function (RouteCollectorProxy $v1Group) {

        require "src/Royal/Routes/v1/mobile/index.php";
    }
);
