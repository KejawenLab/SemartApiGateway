<?php

declare(strict_types=1);

namespace KejawenLab\SemartApiGateway\Service;

use Elastica\Client;
use Elastica\Document;
use Ramsey\Uuid\Uuid;
use Webmozart\Assert\Assert;

/**
 * @author Muhamad Surya Iksanudin<surya.kejawen@gmail.com>
 */
final class ServiceFactory
{
    public const CACHE_KEY = '1d24fe1d841f18d70849097eb5e4d0b57a1c5b18';

    public const INDEX_NAME = 'semart_gateway_services';

    private $redis;

    private $elasticsearch;

    /**
     * @var Service[]
     */
    private $services;

    public function __construct(\Redis $redis, Client $elasticsearch)
    {
        $this->redis = $redis;
        $this->elasticsearch = $elasticsearch;
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
        $serviceIndex = $this->elasticsearch->getIndex(static::INDEX_NAME);
        foreach ($this->services as $service) {
            $services[] = $service->toArray();
            $serviceIndex->updateDocument(new Document(sha1(sprintf('%s_%s', static::CACHE_KEY, $service->getName())), $service->toArray()));
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

    public function update(Service $service): void
    {
        $this->addService($service);
    }

    public function up(Service $service): void
    {
        $service->up();

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
