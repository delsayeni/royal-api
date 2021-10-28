<?php

use Slim\Routing\RouteCollectorProxy;

use Royal\Controllers\Mobile\OrganiserController;


isset($mobileGroup) && $mobileGroup->group(
    "/organiser",
    function (RouteCollectorProxy $organiserGroup) {

        $organiserGroup->post(
            "/login",
            OrganiserController::class . ":Login"
        );

        $organiserGroup->post(
            "/verify/{event_id}",
            OrganiserController::class . ":verifyUser"
        );

        $organiserGroup->post(
            "/manualverify/{event_id}",
            OrganiserController::class . ":manualVerifyUser"
        );

        $organiserGroup->post(
            "/turnstileverify",
            OrganiserController::class . ":turnstileVerifyUser"
        );

        $organiserGroup->post(
            "/testfacemachine",
            OrganiserController::class . ":testFaceMachine"
        );

        $organiserGroup->get(
            "/detachturnstile",
            OrganiserController::class . ":detachTurnStiles"
        );

        $organiserGroup->get(
            "/getevents/{organiser_id}/{offset}/{limit}",
            OrganiserController::class . ":getOrganiserEvent"
        );
    }
);
