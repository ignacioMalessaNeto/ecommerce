<?php

session_start();

require_once("vendor/autoload.php");

use \Slim\Slim;

$app = new Slim();

$app->config('debug', true);

require("site.php");
require("admin.php");
require("admin-categories.php");
require("admin-users.php");
require("admin-products.php");
require("functions.php");
$app->run();
