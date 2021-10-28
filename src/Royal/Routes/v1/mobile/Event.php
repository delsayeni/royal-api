<?php

use Slim\Routing\RouteCollectorProxy;

use Royal\Controllers\Mobile\RoyalController;
use Royal\Middlewares\AuthenticationMiddleware;


isset($mobileGroup) && $mobileGroup->group(
    "",
    function (RouteCollectorProxy $mobileEventGroup) {

        $mobileEventGroup->post(
            "/dohomepage",
            RoyalController::class . ":doHomePage"
        );

        $mobileEventGroup->post(
            "/dopaymentpage",
            RoyalController::class . ":doPaymentPage"
        );

        $mobileEventGroup->post(
            "/dolocalpayment",
            RoyalController::class . ":doLocalPayment"
        );

        $mobileEventGroup->post(
            "/dointernalpayment",
            RoyalController::class . ":doInternalPayment"
        );

        $mobileEventGroup->post(
            "/dointernationalpayment",
            RoyalController::class . ":doInternationalPayment"
        );

        $mobileEventGroup->post(
            "/doprofilepage",
            RoyalController::class . ":doProfilePage"
        );

        $mobileEventGroup->post(
            "/doupdateprofile",
            RoyalController::class . ":doUpdateProfile"
        );

        $mobileEventGroup->post(
            "/dochangepassword",
            RoyalController::class . ":doChangePassword"
        );

        $mobileEventGroup->post(
            "/doadminpage",
            RoyalController::class . ":doAdminPage"
        );

        $mobileEventGroup->post(
            "/changeuserstatus",
            RoyalController::class . ":doChangeUserStatus"
        );

        $mobileEventGroup->post(
            "/deleteregcode",
            RoyalController::class . ":deleteRegCode"
        );

        $mobileEventGroup->post(
            "/generateregcode",
            RoyalController::class . ":generateRegCode"
        );

        $mobileEventGroup->post(
            "/updatetransferstatus",
            RoyalController::class . ":updateTransferStatus"
        );
    }
);
