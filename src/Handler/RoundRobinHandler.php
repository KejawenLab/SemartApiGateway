<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Handler;

use KejawenLab\SemartApiGateway\Route\Route;
use KejawenLab\SemartApiGateway\Service\Service;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RoundRobinHandler implements HandlerInterface
{
    public function handle(Route $route): ?Service
    {
        return $this->getService($route, $route->getCurrentHandler());
    }

    public function getName(): string
    {
        return self::BALANCE_ROUNDROBIN;
    }

    private function getService(Route $route, int $index): ?Service
    {
        $service = $route->getHandler($index);
        if ($service) {
            $route->setCurrentHandler($index);
            if (!$service->isEnabled()) {
                return $this->getService($route, ++$index);
            }
        }

        return $service;
    }
}
