<?php

define('GATEWAY_ROOT', dirname(__DIR__));

require GATEWAY_ROOT.'/vendor/autoload.php';

use KejawenLab\SemartApiGateway\Gateway;
use Symfony\Component\HttpFoundation\Request;

$redis = new Redis();
$redis->connect('localhost');

$app = new Gateway($redis);

$GLOBALS['app'] = $app;

$app->handle(Request::createFromGlobals())->send();
