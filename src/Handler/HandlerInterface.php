<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Handler;

use KejawenLab\SemartApiGateway\Route\Route;
use KejawenLab\SemartApiGateway\Service\Service;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
interface HandlerInterface
{
    public const BALANCE_STICKY = 'sticky';

    public const BALANCE_RANDOM = 'random';

    public const BALANCE_ROUNDROBIN = 'roundrobin';

    public const BALANCE_WEIGHT = 'weight';

    public function handle(Route $route): ?Service;

    public function getName(): string;
}
