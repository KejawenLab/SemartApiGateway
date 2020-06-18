<?php

define('GATEWAY_ROOT', dirname(__DIR__));

require GATEWAY_ROOT.'/vendor/autoload.php';

use KejawenLab\SemartApiGateway\Gateway;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

if (!isset($_SERVER['SEMART_ENV']) || !isset($_SERVER['SEMART_REDIST_HOST'])) {
    $dotEnv = new Dotenv();
    $dotEnv->load(sprintf('%s/.env', GATEWAY_ROOT));
}

$redis = new Redis();
$redis->connect($_SERVER['SEMART_REDIST_HOST']);

$app = new Gateway($redis, $_SERVER['SEMART_ENV']);

$GLOBALS['app'] = $app;

$app->handle(Request::createFromGlobals())->send();
