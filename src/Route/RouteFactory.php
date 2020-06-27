<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Route;

use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class RouteFactory
{
    private const CACHE_KEY = '28e43937becaea8523f5522bea5b38c789ed23d4';

    private $redis;

    /**
     * @var Route[]
     */
    private $routes;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
        $this->routes = [];
    }

    public function populate(): void
    {
        if (!$routes = $this->redis->get(static::CACHE_KEY)) {
            return;
        }

        foreach (unserialize($routes) as $route) {
            $this->addRoute(Route::createFromArray($route));
        }
    }

    public function persist(): void
    {
        $routes = [];
        foreach ($this->routes as $route) {
            $routes[] = $route->toArray();
        }

        $this->redis->set(static::CACHE_KEY, serialize($routes));
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
}
