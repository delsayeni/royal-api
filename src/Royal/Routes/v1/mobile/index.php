<?php

use Slim\Routing\RouteCollectorProxy;

isset($v1Group) && $v1Group->group(
    "/m",
    function (RouteCollectorProxy $mobileGroup) {

        require "src/Royal/Routes/v1/mobile/Auth.php";
        require "src/Royal/Routes/v1/mobile/Event.php";
        require "src/Royal/Routes/v1/mobile/Organiser.php";
    }
);
