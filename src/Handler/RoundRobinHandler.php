<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Handler;

use KejawenLab\SemartApiGateway\Route\Route;
use KejawenLab\SemartApiGateway\Service\Service;
use KejawenLab\SemartApiGateway\Service\ServiceFactory;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RoundRobinHandler implements HandlerInterface
{
    private $serviceFactory;

    public function __construct(ServiceFactory $serviceFactory)
    {
        $this->serviceFactory = $serviceFactory;
    }

    public function handle(Route $route): ?Service
    {
        $current = $route->getCurrentHandler();

        return $this->getService($route, ++$current);
    }

    public function getName(): string
    {
        return self::BALANCE_ROUNDROBIN;
    }

    private function getService(Route $route, int $index): ?Service
    {
        $service = $route->getHandler($index);
        if ($service) {
            $service = $this->serviceFactory->get($service->getName());
            $route->setCurrentHandler($index);
            if (!$service->isEnabled()) {
                return $this->getService($route, ++$index);
            }
        }

        return $service;
    }
}
