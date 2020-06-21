<?php

define('GATEWAY_ROOT', dirname(__DIR__));

require GATEWAY_ROOT.'/vendor/autoload.php';

use Elastica\Client;
use KejawenLab\SemartApiGateway\Gateway;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

if (!isset($_SERVER['SEMART_ENV']) || !isset($_SERVER['SEMART_REDIST_HOST'])) {
    $dotEnv = new Dotenv();
    $dotEnv->load(sprintf('%s/.env', GATEWAY_ROOT));
}

$redis = new Redis();
$redis->connect($_SERVER['SEMART_REDIST_HOST'], $_SERVER['SEMART_REDIST_PORT']);

$app = new Gateway($redis, new Client([
    'host' => $_SERVER['SEMART_ELASTICSEARCH_HOST'],
    'port' => $_SERVER['SEMART_ELASTICSEARCH_PORT'],
]), $_SERVER['SEMART_ENV']);

$GLOBALS['app'] = $app;

$app->handle(Request::createFromGlobals())->send();
