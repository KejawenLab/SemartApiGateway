<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Aggregate;

use Symfony\Component\Routing\RouteCollection;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ApiAggregationFactory
{
    public function __construct(array $agregates = [])
    {
    }

    public function registerRoutes(RouteCollection $routeCollection): RouteCollection
    {
        return $routeCollection;
    }
}
