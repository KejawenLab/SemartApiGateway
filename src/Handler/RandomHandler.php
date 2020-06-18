<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Handler;

use KejawenLab\SemartApiGateway\Route\Route;
use KejawenLab\SemartApiGateway\Service\Service;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RandomHandler implements HandlerInterface
{
    public function handle(Route $route): ?Service
    {
        return $this->getService($route, $this->getIndex($route));
    }

    public function getName(): string
    {
        return self::BALANCE_RANDOM;
    }

    private function getService(Route $route, int $index): ?Service
    {
        $service = $route->getHandler($index);
        if ($service) {
            $route->setCurrentHandler($index);
            if (!$service->isEnabled()) {
                return $this->getService($route, $this->getIndex($route));
            }
        }

        return $service;
    }

    private function getIndex(Route $route): int
    {
        return rand(0, count($route->getHandlers()) - 1);
    }
}
