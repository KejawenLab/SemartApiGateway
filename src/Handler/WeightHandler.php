<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Handler;

use KejawenLab\SemartApiGateway\Route\Route;
use KejawenLab\SemartApiGateway\Service\Service;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class WeightHandler implements HandlerInterface
{
    public function handle(Route $route): ?Service
    {
        return $this->getService($route, $route->getCurrentHandler());
    }

    public function getName(): string
    {
        return self::BALANCE_WEIGHT;
    }

    private function getService(Route $route, int $index): ?Service
    {
        if (!$route->isSorted()) {
            $services = $route->getHandlers();
            usort($services, function ($prev, $next) {
                /** @var Service $prev */
                /** @var Service $next */
                if ($prev->getWeight() === $next->getWeight()) {
                    return 0;
                }

                return ($prev->getWeight() > $next->getWeight()) ? -1 : 1;
            });

            $route->setHandlers($services);
            $route->sorted();
        }

        if (!array_key_exists($index, $route->getHandlers())) {
            $index = 0;
        }

        $service = $route->getHandler($index);
        if ($service) {
            if (!$service->isEnabled()) {
                return $this->getService($route, ++$index);
            }

            if (0 === $service->getHit() % $service->getWeight()) {
                ++$index;
                if (!array_key_exists($index, $route->getHandlers())) {
                    $index = 0;
                }

                $route->setCurrentHandler($index);

                return $route->getHandler($index);
            }

            $route->setCurrentHandler($index);

            return $route->getHandler($index);
        }

        return $service;
    }
}
