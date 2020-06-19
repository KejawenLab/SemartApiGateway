<?php

use KejawenLab\SemartApiGateway\Gateway;
use KejawenLab\SemartApiGateway\Service\Service;
use KejawenLab\SemartApiGateway\Service\Statistic;

function app(): Gateway
{
    return $GLOBALS['app'];
}

function add_to_stat(Service $service, array $data): void
{
    /** @var Statistic $statistic */
    $statistic = app()['gateway.statistic'];
    $statistic->stat($service, $data);
}
