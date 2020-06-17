<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Route;

use KejawenLab\SemartApiGateway\Handler\HandlerInterface as Balancer;
use KejawenLab\SemartApiGateway\Service\Service;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
class Route
{
    private $name;

    private $path;

    private $methods;

    private $priority;

    private $public;

    private $cacheLifetime;

    private $balanceMethod;

    private $requirements;

    private $handlers;

    private $currentHandler;

    public function __construct(
        string $name,
        string $path,
        array $handlers,
        array $methods = ['GET'],
        string $balanceMethod = Balancer::BALANCE_RANDOM,
        int $priority = 0,
        bool $public = false,
        array $requirements = [],
        int $cacheLifetime = 0
    ) {
        $this->name = $name;
        $this->path = $path;
        $this->methods = $methods;
        $this->balanceMethod = $balanceMethod;
        $this->priority = $priority;
        $this->public = $public;
        $this->requirements = $requirements;
        $this->cacheLifetime = $cacheLifetime;
        $this->currentHandler = null;

        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    public static function createFromArray(array $route): self
    {
        Assert::keyExists($route, 'name');
        Assert::keyExists($route, 'path');
        Assert::keyExists($route, 'handlers');
        Assert::isArray($route['handlers']);

        $methods = ['GET'];
        $balanceMethod = Balancer::BALANCE_RANDOM;
        $priority = 1;
        $public = false;
        $requirements = [];
        $cacheLifetime = 0;

        if (array_key_exists('methods', $route) && is_array($route['methods'])) {
            $methods = $route['methods'];
        }

        if (array_key_exists('method', $route) && is_string($route['method'])) {
            $methods = (array) $route['method'];
        }

        if (array_key_exists('balance', $route) && in_array($route['balance'], [Balancer::BALANCE_RANDOM, Balancer::BALANCE_ROUNDROBIN, Balancer::BALANCE_WEIGHT])) {
            $balanceMethod = $route['balance'];
        }

        if (array_key_exists('balance_method', $route) && in_array($route['balance_method'], [Balancer::BALANCE_RANDOM, Balancer::BALANCE_ROUNDROBIN, Balancer::BALANCE_WEIGHT])) {
            $balanceMethod = $route['balance_method'];
        }

        if (array_key_exists('priority', $route) && is_int($route['priority'])) {
            $priority = $route['priority'];
        }

        if (array_key_exists('public', $route) && is_bool($route['public'])) {
            $public = $route['public'];
        }

        if (array_key_exists('requirements', $route) && is_array($route['requirements'])) {
            $requirements = $route['requirements'];
        }

        if (array_key_exists('cache_lifetime', $route) && is_int($route['cache_lifetime'])) {
            $cacheLifetime = $route['cache_lifetime'];
        }

        return new self($route['name'], $route['path'], $route['handlers'], $methods, $balanceMethod, $priority, $public, $requirements, $cacheLifetime);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'path' => $this->getPath(),
            'handlers' => $this->getHandlers(),
            'methods' => $this->getMethods(),
            'balance_method' => $this->getBalanceMethod(),
            'priority' => $this->getPriority(),
            'public' => $this->isPublic(),
            'requirements' => $this->getRequirements(),
            'cache_lifetime' => $this->getCacheLifetime(),
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function getCacheLifetime(): int
    {
        return $this->cacheLifetime;
    }

    public function getBalanceMethod(): string
    {
        return $this->balanceMethod;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getCurrentHandler(): int
    {
        return $this->currentHandler ?? -1;
    }

    public function setCurrentHandler(int $index): void
    {
        $this->currentHandler = $index;
    }

    public function getHandler(int $index): ?Service
    {
        $handlers = $this->getHandlers();
        if (array_key_exists($index, $handlers)) {
            return $handlers[$index];
        }

        return null;
    }

    /**
     * @return Service[]
     */
    public function getHandlers(): array
    {
        return array_values($this->handlers);
    }

    private function addHandler(Service $service): void
    {
        $this->handlers[$service->getName()] = $service;
    }
}
