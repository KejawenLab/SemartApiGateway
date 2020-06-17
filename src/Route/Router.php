<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Route;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route as SymfonyRoute;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class Router
{
    public static function build(RouteFactory $routeFactory, string $prefix = ''): RouteCollection
    {
        $routeCollection = new RouteCollection();
        $routes = $routeFactory->routes();
        foreach ($routes as $key => $route) {
            $routeCollection->add($key, new SymfonyRoute(
                sprintf('%s%s', $prefix, $route->getPath()),
                [], $route->getRequirements(), [], null, [], $route->getMethods()
            ), $route->getPriority());
        }

        return $routeCollection;
    }
}
