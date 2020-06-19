<?php

use KejawenLab\SemartApiGateway\Gateway;
use KejawenLab\SemartApiGateway\Service\Service;
use KejawenLab\SemartApiGateway\Service\Statistic;
use Symfony\Component\HttpFoundation\Response;

function app(): Gateway
{
    return $GLOBALS['app'];
}

function add_to_stat(Service $service, Response $response): void
{
    /** @var Statistic $statistic */
    $statistic = app()['gateway.statistic'];
    $statistic->stat($service, $response);
}
