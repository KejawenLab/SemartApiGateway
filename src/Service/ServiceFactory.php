<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Service;

use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ServiceFactory
{
    private const CACHE_KEY = '1d24fe1d841f18d70849097eb5e4d0b57a1c5b18';

    private $redis;

    /**
     * @var Service[]
     */
    private $services;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
        $this->services = [];
    }

    public function populate(): void
    {
        if (!$services = $this->redis->get(static::CACHE_KEY)) {
            return;
        }

        foreach (unserialize($services) as $service) {
            $this->addService(Service::createFromArray($service));
        }
    }

    public function persist(): void
    {
        $services = [];
        foreach ($this->services as $service) {
            $services[] = $service->toArray();
        }

        $this->redis->set(static::CACHE_KEY, serialize($services));

        app()->pool(static::CACHE_KEY);
    }

    public function get(string $name): Service
    {
        Assert::keyExists($this->services, $name, 'Service "%s" not found.');

        return $this->services[$name];
    }

    /**
     * @return Service[]
     */
    public function getServices(): array
    {
        return $this->services;
    }

    public function down(Service $service): void
    {
        $service->down();

        $this->addService($service);
    }

    public function up(Service $service): void
    {
        $service->up();

        $this->addService($service);
    }

    public function disabled(Service $service): void
    {
        $service->disabled();

        $this->addService($service);
    }

    public function enabled(Service $service): void
    {
        $service->enabled();

        $this->addService($service);
    }

    public function addService(Service $service): void
    {
        $this->services[$service->getName()] = $service;
    }

    public function __destruct()
    {
        $this->persist();
    }
}
