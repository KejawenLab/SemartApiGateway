<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Service;

use KejawenLab\SemartApiGateway\Handler\HandlerFactory;
use KejawenLab\SemartApiGateway\Route\RouteFactory;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class Resolver
{
    private $routeFactory;

    private $handlerFactory;

    public function __construct(RouteFactory $routeFactory, HandlerFactory $handlerFactory)
    {
        $this->routeFactory = $routeFactory;
        $this->handlerFactory = $handlerFactory;
    }

    public function resolve(string $routeName): ?Service
    {
        $service = $this->handlerFactory->handle($this->routeFactory->get($routeName));
        if ($service) {
            $service->hit();
        }

        return $service;
    }
}
