<?php

require "vendor/autoload.php";

use Royal\Domain\Constants;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__."/..");
$dotenv->load();

$db_host = $_ENV["DB_HOST"];
$db_name = $_ENV["DB_NAME"];
$db_user = $_ENV["DB_USER"];
$db_pass = $_ENV["DB_PWD"];

$basePath = $_ENV["BASE_PATH"];
