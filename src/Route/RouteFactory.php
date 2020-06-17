<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Route;

use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RouteFactory
{
    private const CACHE_KEY = '1d24fe1d841f18d70849097eb5e4d0b57a1c5b18';

    private $redis;

    /**
     * @var Route[]
     */
    private $routes;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    public function populate(): void
    {
        $routes = $this->redis->get(static::CACHE_KEY);
        foreach ($routes as $route) {
            $this->addRoute(Route::createFromArray($route));
        }
    }

    public function persist(): void
    {
        $routes = [];
        foreach ($this->routes as $route) {
            $routes[] = $route->toArray();
        }

        $this->redis->set(static::CACHE_KEY, $routes);
        app()->pool(static::CACHE_KEY);
    }

    /**
     * @return Route[]
     */
    public function routes(): array
    {
        return $this->routes;
    }

    public function get(string $name): Route
    {
        Assert::keyExists($this->routes, $name, 'Route "%s" not found.');

        return $this->routes[$name];
    }

    public function addRoute(Route $route): void
    {
        $this->routes[$route->getName()] = $route;
    }

    public function __destruct()
    {
        $this->persist();
    }
}
