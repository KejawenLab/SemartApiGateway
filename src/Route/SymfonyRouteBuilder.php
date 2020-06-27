<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Route;

use KejawenLab\SemartApiGateway\Aggregate\ApiAggregationFactory;
use KejawenLab\SemartApiGateway\Gateway;
use KejawenLab\SemartApiGateway\Service\ServiceStatus;
use KejawenLab\SemartApiGateway\Service\Statistic;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Symfony\Component\Routing\RouteCollection;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class SymfonyRouteBuilder
{
    public static function build(Gateway $container, ApiAggregationFactory $aggregateFactory): RouteCollection
    {
        /** @var RouteFactory $routeFactory */
        $routeFactory = $container->get(RouteFactory::class);
        $prefix = $container->get('gateway.prefix');
        $routeCollection = new RouteCollection();
        foreach ($routeFactory->routes() as $route) {
            $routeCollection->add(
                $route->getName(),
                new SymfonyRoute(
                    sprintf('%s%s', $prefix, $route->getPath()),
                    [],
                    $route->getRequirements(),
                    [],
                    null,
                    [],
                    $route->getMethods()
                ),
                $route->getPriority()
            );
        }

        $routeCollection->add(Statistic::ROUTE_NAME, new SymfonyRoute(Statistic::ROUTE_PATH, [], [], [], null, [], ['GET']));
        $routeCollection->add(ServiceStatus::ROUTE_NAME, new SymfonyRoute(ServiceStatus::ROUTE_PATH, [], [], [], null, [], ['GET']));
        $routeCollection->add(Gateway::SEMART_GATEWAY_HOME, new SymfonyRoute('/', [], [], [], null, [], ['GET']));

        return $aggregateFactory->registerRoutes($routeCollection, $container->get('gateway.aggregates'), $prefix);
    }
}
