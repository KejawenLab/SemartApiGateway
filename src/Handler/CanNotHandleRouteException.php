<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Handler;

use KejawenLab\SemartApiGateway\Route\Route;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
class CanNotHandleRouteException extends \InvalidArgumentException
{
    public function __construct(Route $route)
    {
        parent::__construct(sprintf('Route "%s" with path "%s" is impossible to handle', $route->getName(), $route->getPath()));
    }
}
